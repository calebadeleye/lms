<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EnrolmentController extends Controller
{
    public function __construct(private CourseCompletionService $completion) {}

    /** GET /my/enrolments — student's "My Courses" list. */
    public function index(Request $request)
    {
        $enrolments = Enrolment::where('user_id', $request->user()->id)
            ->with('course.category', 'course.instructors:id,name')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('enrolled_at')
            ->get();

        return response()->json(['data' => $enrolments->map(fn (Enrolment $e) => [
            'id' => $e->id,
            'status' => $e->status,
            'enrolled_at' => $e->enrolled_at,
            'completed_at' => $e->completed_at,
            'completion_percent' => $this->completion->recompute($e),
            'course' => [
                'id' => $e->course->id,
                'title' => $e->course->title,
                'slug' => $e->course->slug,
                'thumbnail_url' => $e->course->thumbnailUrl(),
                'category' => $e->course->category?->only(['id', 'name']),
                'instructors' => $e->course->instructors->map->only(['id', 'name']),
            ],
        ])]);
    }

    /** GET /courses/{course}/learner-enrolments — instructor/admin roster. */
    public function forCourse(string $courseId)
    {
        $enrolments = Enrolment::where('course_id', $courseId)
            ->with('user:id,name,email', 'certificate:id,enrolment_id')
            ->orderByDesc('enrolled_at')
            ->get();

        return response()->json(['data' => $enrolments->map(fn (Enrolment $e) => [
            'id' => $e->id,
            'status' => $e->status,
            'enrolled_at' => $e->enrolled_at,
            'completed_at' => $e->completed_at,
            'completion_percent' => $this->completion->recompute($e),
            'has_certificate' => $e->certificate !== null,
            'user' => $e->user->only(['id', 'name', 'email']),
        ])]);
    }
}
