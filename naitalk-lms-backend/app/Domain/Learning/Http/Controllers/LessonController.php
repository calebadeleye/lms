<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\CourseModule;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Membership\Services\MembershipGateService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function store(Request $request, string $moduleId)
    {
        $module = CourseModule::findOrFail($moduleId);
        $data = $this->validateLesson($request);

        $lesson = $module->lessons()->create([
            ...$data,
            'sort_order' => $module->lessons()->max('sort_order') + 1,
        ]);

        return response()->json(['data' => $lesson], 201);
    }

    public function update(Request $request, string $lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);
        $data = $this->validateLesson($request, partial: true);

        $lesson->update($data);

        return response()->json(['data' => $lesson->fresh()]);
    }

    public function destroy(string $lessonId)
    {
        Lesson::findOrFail($lessonId)->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    /**
     * Student-facing lesson content. Enforces preview/enrolment/drip
     * access — a locked lesson returns 403 with the unlock condition, never
     * the content itself.
     */
    public function show(Request $request, string $lessonId, MembershipGateService $membershipGate)
    {
        $lesson = Lesson::with('courseModule.course.modules.lessons')->findOrFail($lessonId);
        $user = $request->user();

        if ($failure = $this->authorizeAccess($lesson, $user, $membershipGate)) {
            return $failure;
        }

        if ($lesson->is_preview) {
            return response()->json(['data' => $this->present($lesson)]);
        }

        $enrolment = Enrolment::where('course_id', $lesson->courseModule->course_id)->where('user_id', $user->id)->firstOrFail();

        $progress = LessonProgress::firstOrCreate(
            ['enrolment_id' => $enrolment->id, 'lesson_id' => $lesson->id],
            ['status' => 'not_started']
        );

        if ($progress->status === 'not_started') {
            $progress->update(['status' => 'in_progress']);
        }

        return response()->json(['data' => [
            ...$this->present($lesson),
            'progress' => [
                'status' => $progress->status,
                'video_position_seconds' => $progress->video_position_seconds,
            ],
        ]]);
    }

    /**
     * Streams a lesson's uploaded material (PDF/PPT/etc) — gated by the
     * exact same preview/enrolment/membership/drip rules as show(), since
     * this is just another view of the same lesson's content.
     */
    public function downloadMaterial(Request $request, string $lessonId, MembershipGateService $membershipGate)
    {
        $lesson = Lesson::with('courseModule.course')->findOrFail($lessonId);

        if ($failure = $this->authorizeAccess($lesson, $request->user(), $membershipGate)) {
            return $failure;
        }

        $materialPath = $lesson->content['material_path'] ?? null;

        if (! $materialPath || ! Storage::disk('uploads')->exists($materialPath)) {
            abort(404);
        }

        $filename = $lesson->content['material_filename'] ?? basename($materialPath);

        return Storage::disk('uploads')->download($materialPath, $filename);
    }

    /**
     * Admin upload for a 'file'-type lesson's material (PDF/PPT/etc). Stored
     * on the private `uploads` disk — downloadMaterial() above is the only
     * way to read it back, so access stays gated by real enrolment rather
     * than a guessable public URL. `video_path` is set to that protected
     * route (relative, proxied through the frontend's own origin) so the
     * existing student-facing lesson player needs no changes at all.
     */
    public function uploadMaterial(Request $request, string $lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,zip', 'max:20480'],
        ]);

        $file = $request->file('file');
        $directory = "course-materials/{$lesson->id}";
        $storedName = Str::uuid().'.'.$file->extension();

        $file->storeAs($directory, $storedName, ['disk' => 'uploads']);

        $lesson->update([
            'video_path' => "/api/v1/lessons/{$lesson->id}/material",
            'content' => [
                ...(is_array($lesson->content) ? $lesson->content : []),
                'material_path' => "{$directory}/{$storedName}",
                'material_filename' => $file->getClientOriginalName(),
            ],
        ]);

        return response()->json(['data' => $lesson->fresh()]);
    }

    /** Null if accessible; otherwise the 403 JSON response to return as-is. */
    private function authorizeAccess(Lesson $lesson, ?User $user, MembershipGateService $membershipGate): ?JsonResponse
    {
        if ($lesson->is_preview) {
            return null;
        }

        $enrolment = $user
            ? Enrolment::where('course_id', $lesson->courseModule->course_id)->where('user_id', $user->id)->first()
            : null;

        if (! $enrolment) {
            return response()->json(['errors' => [['code' => 'not_enrolled', 'message' => 'Enrol in this course to view this lesson.']]], 403);
        }

        // Membership-only courses are re-checked on every view, not just at
        // enrolment time — a lapsed membership should actually revoke
        // access, matching how a paid course's access isn't re-sold either.
        if (! $membershipGate->canAccessCourse($user, $lesson->courseModule->course)) {
            return response()->json([
                'errors' => [['code' => 'membership_required', 'message' => 'Your membership has expired. Renew to continue this course.']],
            ], 403);
        }

        if (! $lesson->isAvailableFor($enrolment)) {
            $unlocksAt = $enrolment->enrolled_at->addDays($lesson->available_after_days);

            return response()->json([
                'errors' => [['code' => 'lesson_locked', 'message' => "This lesson unlocks on {$unlocksAt->toDateString()}."]],
            ], 403);
        }

        return null;
    }

    private function present(Lesson $lesson): array
    {
        $course = $lesson->courseModule->course;

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'type' => $lesson->type,
            'content' => $lesson->content,
            'video_path' => $lesson->video_path,
            'duration_seconds' => $lesson->duration_seconds,
            'is_mandatory' => $lesson->is_mandatory,
            'course_id' => $course->id,
            'course_title' => $course->title,
            'course_slug' => $course->slug,
            'modules' => $course->modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn ($l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'type' => $l->type,
                    'is_current' => $l->id === $lesson->id,
                ]),
            ]),
        ];
    }

    private function validateLesson(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:video,rich_text,audio,file,external_link,quiz,assignment,live'],
            'content' => ['nullable', 'array'],
            'video_path' => ['nullable', 'string', 'max:2048'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'available_after_days' => ['nullable', 'integer', 'min:0'],
        ];

        if ($partial) {
            $rules = collect($rules)->mapWithKeys(fn ($rule, $key) => [$key => array_merge(['sometimes'], (array) $rule)])->all();
        }

        return $request->validate($rules);
    }
}
