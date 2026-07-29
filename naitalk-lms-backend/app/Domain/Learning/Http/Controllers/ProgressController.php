<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Services\CourseCompletionService;
use App\Domain\Learning\Services\ProgressService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(
        private ProgressService $progress,
        private CourseCompletionService $completion,
    ) {}

    public function updatePosition(Request $request, string $lessonId)
    {
        $data = $request->validate(['position_seconds' => ['required', 'integer', 'min:0']]);

        [$lesson, $enrolment] = $this->resolve($request, $lessonId);

        $progress = $this->progress->updatePlaybackPosition($enrolment, $lesson, $data['position_seconds']);

        return response()->json(['data' => [
            'status' => $progress->status,
            'video_position_seconds' => $progress->video_position_seconds,
            'course_completion_percent' => $this->completion->recompute($enrolment),
        ]]);
    }

    public function markComplete(Request $request, string $lessonId)
    {
        [$lesson, $enrolment] = $this->resolve($request, $lessonId);

        $progress = $this->progress->markComplete($enrolment, $lesson);

        return response()->json(['data' => [
            'status' => $progress->status,
            'course_completion_percent' => $this->completion->recompute($enrolment),
        ]]);
    }

    /** @return array{0: Lesson, 1: Enrolment} */
    private function resolve(Request $request, string $lessonId): array
    {
        $lesson = Lesson::with('courseModule')->findOrFail($lessonId);

        $enrolment = Enrolment::where('course_id', $lesson->courseModule->course_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return [$lesson, $enrolment];
    }
}
