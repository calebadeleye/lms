<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Assignment;
use App\Domain\Learning\Models\AssignmentSubmission;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Services\ProgressService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private ProgressService $progress) {}

    public function save(Request $request, string $lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'max_points' => ['integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
        ]);

        $assignment = Assignment::updateOrCreate(['lesson_id' => $lesson->id], $data);

        return response()->json(['data' => $assignment], 201);
    }

    /** Student-facing — assignment details plus the student's own submission, if any. */
    public function show(Request $request, string $lessonId)
    {
        $assignment = Assignment::where('lesson_id', $lessonId)->firstOrFail();
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json(['data' => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'max_points' => $assignment->max_points,
            'due_date' => $assignment->due_date,
            'submission' => $submission,
        ]]);
    }

    public function submit(Request $request, string $lessonId)
    {
        $assignment = Assignment::where('lesson_id', $lessonId)->with('lesson.courseModule')->firstOrFail();

        $data = $request->validate([
            'content_text' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $enrolment = Enrolment::where('course_id', $assignment->lesson->courseModule->course_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $submission = AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => $request->user()->id],
            [
                'enrolment_id' => $enrolment->id,
                'content_text' => $data['content_text'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'submitted_at' => now(),
                // Resubmitting clears any previous grade — it's a new answer.
                'grade' => null,
                'feedback' => null,
                'graded_at' => null,
                'graded_by' => null,
            ]
        );

        // Submission itself (not the grade) satisfies the lesson — matches
        // ProgressService's documented rule for assignment-type lessons.
        $this->progress->markCompleteBySystem($enrolment, $assignment->lesson);

        return response()->json(['data' => $submission]);
    }

    /** Instructor/admin roster of submissions for grading. */
    public function submissions(string $lessonId)
    {
        $assignment = Assignment::where('lesson_id', $lessonId)->firstOrFail();

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->with('user:id,name,email')
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json(['data' => $submissions]);
    }

    public function grade(Request $request, string $submissionId)
    {
        $submission = AssignmentSubmission::findOrFail($submissionId);

        $data = $request->validate([
            'grade' => ['required', 'integer', 'min:0'],
            'feedback' => ['nullable', 'string'],
        ]);

        $submission->update([
            'grade' => $data['grade'],
            'feedback' => $data['feedback'] ?? null,
            'graded_at' => now(),
            'graded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $submission->fresh()]);
    }
}
