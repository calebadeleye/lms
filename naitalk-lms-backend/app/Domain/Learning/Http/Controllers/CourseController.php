<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\EnrolmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function __construct(private EnrolmentService $enrolments) {}

    /** Public catalogue — published courses only. */
    public function index(Request $request)
    {
        $courses = Course::query()
            ->where('status', 'published')
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->string('pricing_type')->isNotEmpty(), fn ($q) => $q->where('pricing_type', $request->string('pricing_type')))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where('title', 'like', '%'.$request->string('search').'%'))
            ->with('category', 'instructors:id,name')
            ->withCount('enrolments')
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'data' => collect($courses->items())->map(fn (Course $c) => $this->summarize($c)),
            'meta' => ['pagination' => [
                'page' => $courses->currentPage(), 'per_page' => $courses->perPage(), 'total' => $courses->total(),
            ]],
        ]);
    }

    /** Public detail by slug — locks lesson content the visitor can't see yet. */
    public function show(Request $request, string $slug)
    {
        $course = Course::where('slug', $slug)->where('status', 'published')
            ->with('category', 'instructors:id,name', 'modules.lessons')
            ->firstOrFail();

        $user = $request->user();
        $enrolment = $user
            ? Enrolment::where('course_id', $course->id)->where('user_id', $user->id)->first()
            : null;

        return response()->json(['data' => [
            ...$this->summarize($course),
            'description' => $course->description,
            'reviews_count' => $course->reviews()->count(),
            'is_enrolled' => $enrolment !== null,
            'modules' => $course->modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(function ($lesson) use ($enrolment) {
                    $unlocked = $lesson->is_preview || ($enrolment && $lesson->isAvailableFor($enrolment));

                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'type' => $lesson->type,
                        'duration_seconds' => $lesson->duration_seconds,
                        'is_preview' => $lesson->is_preview,
                        'is_mandatory' => $lesson->is_mandatory,
                        'locked' => ! $unlocked,
                    ];
                }),
            ]),
        ]]);
    }

    /** Full course + modules + lessons (any status) for the course builder UI. */
    public function adminShow(string $courseId)
    {
        $course = Course::with('category', 'instructors:id,name', 'modules.lessons.quiz.questions.options', 'modules.lessons.assignment')
            ->withCount('enrolments')
            ->findOrFail($courseId);

        return response()->json(['data' => [...$course->toArray(), 'thumbnail_url' => $course->thumbnailUrl()]]);
    }

    public function adminIndex(Request $request)
    {
        $courses = Course::query()
            ->with('category')
            ->withCount('enrolments')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($courses->items())->map(fn (Course $c) => [
                ...$c->toArray(),
                'thumbnail_url' => $c->thumbnailUrl(),
            ]),
            'meta' => ['pagination' => ['page' => $courses->currentPage(), 'per_page' => $courses->perPage(), 'total' => $courses->total()]],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCourse($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        // Explicit rather than relying on the DB column default — Eloquent
        // doesn't refresh in-memory attributes from SQL-level defaults after
        // create(), so the returned model's `status`/`currency` would
        // otherwise be null (the latter isn't in validateCourse()'s rules
        // at all — no course-creation UI lets an admin choose a currency
        // yet — so it's always relying on this default).
        $data['status'] = 'draft';
        $data['currency'] ??= 'NGN';

        $course = Course::create($data);

        return response()->json(['data' => $course], 201);
    }

    public function update(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $this->validateCourse($request, partial: true);

        $course->update($data);

        return response()->json(['data' => $course->fresh()]);
    }

    public function uploadThumbnail(Request $request, string $courseId)
    {
        $request->validate(['file' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096']]);

        $course = Course::findOrFail($courseId);
        $file = $request->file('file');
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $directory = "courses/{$course->id}";

        // Old thumbnail cleaned up first so a re-upload in a different
        // format (e.g. .png replacing a .jpg) doesn't leave the stale file
        // sitting on disk under a filename nothing points to anymore.
        if ($course->thumbnail_path) {
            Storage::disk('uploads')->delete($course->thumbnail_path);
        }

        $file->storeAs($directory, "thumbnail.{$extension}", ['disk' => 'uploads']);
        $course->update(['thumbnail_path' => "{$directory}/thumbnail.{$extension}"]);

        return response()->json(['data' => ['thumbnail_url' => $course->fresh()->thumbnailUrl()]]);
    }

    public function publish(string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $course->update(['status' => 'published', 'published_at' => $course->published_at ?? now()]);

        return response()->json(['data' => $course->fresh()]);
    }

    public function unpublish(string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $course->update(['status' => 'draft']);

        return response()->json(['data' => $course->fresh()]);
    }

    public function destroy(string $courseId)
    {
        Course::findOrFail($courseId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    public function attachInstructor(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['in:primary,co_instructor'],
        ]);

        $course->instructors()->syncWithoutDetaching([
            $data['user_id'] => ['role' => $data['role'] ?? 'co_instructor'],
        ]);

        return response()->json(['data' => $course->fresh('instructors')]);
    }

    public function detachInstructor(string $courseId, string $userId)
    {
        Course::findOrFail($courseId)->instructors()->detach($userId);

        return response()->json(['data' => ['success' => true]]);
    }

    /**
     * Handles the two instant-access paths only (free, membership_only).
     * Paid courses go through CheckoutController::course() instead — see
     * ARCHITECTURE.md §11.
     */
    public function enrol(Request $request, string $courseId, \App\Domain\Membership\Services\MembershipGateService $membershipGate)
    {
        $course = Course::where('status', 'published')->findOrFail($courseId);

        if ($course->pricing_type === 'paid') {
            throw ValidationException::withMessages([
                'course' => ['This course requires payment — use the checkout endpoint instead.'],
            ]);
        }

        if ($course->pricing_type === 'membership_only' && ! $membershipGate->canAccessCourse($request->user(), $course)) {
            throw ValidationException::withMessages([
                'course' => ['This course is only available to active members.'],
            ]);
        }

        $source = $course->pricing_type === 'membership_only' ? 'membership' : 'free';

        try {
            $enrolment = $this->enrolments->enroll($request->user(), $course, $source);
        } catch (\App\Domain\Learning\Exceptions\CourseNotFreeException $e) {
            throw ValidationException::withMessages(['course' => [$e->getMessage()]]);
        }

        return response()->json(['data' => $enrolment], 201);
    }

    private function validateCourse(Request $request, bool $partial = false): array
    {
        $rules = [
            'category_id' => ['nullable', 'exists:course_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['required', 'in:free,paid,membership_only'],
            'price_cents' => ['integer', 'min:0'],
            'difficulty_level' => ['in:beginner,intermediate,advanced'],
            'tags' => ['array'],
            'drip_type' => ['in:none,scheduled'],
        ];

        if ($partial) {
            $rules = collect($rules)->map(fn ($rule) => array_merge(['sometimes'], (array) $rule))->all();
        }

        return $request->validate($rules);
    }

    private function summarize(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'excerpt' => $course->excerpt,
            'thumbnail_url' => $course->thumbnailUrl(),
            'pricing_type' => $course->pricing_type,
            'price_cents' => $course->price_cents,
            'currency' => $course->currency,
            'difficulty_level' => $course->difficulty_level,
            'category' => $course->category?->only(['id', 'name', 'slug']),
            'instructors' => $course->instructors->map->only(['id', 'name']),
            'average_rating' => $course->averageRating(),
            'enrolments_count' => $course->enrolments_count ?? 0,
            'status' => $course->status,
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
