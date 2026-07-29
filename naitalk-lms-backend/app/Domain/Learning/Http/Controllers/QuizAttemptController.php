<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Learning\Models\QuizAttempt;
use App\Domain\Learning\Services\QuizGradingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function __construct(private QuizGradingService $grading) {}

    public function start(Request $request, string $lessonId)
    {
        $quiz = Quiz::where('lesson_id', $lessonId)->firstOrFail();

        $enrolment = Enrolment::where('course_id', $quiz->lesson->courseModule->course_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $attempt = $this->grading->startAttempt($request->user(), $enrolment, $quiz);

        return response()->json(['data' => $attempt], 201);
    }

    public function submit(Request $request, string $attemptId)
    {
        $attempt = QuizAttempt::where('user_id', $request->user()->id)->findOrFail($attemptId);

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.selected_option_ids' => ['array'],
            'answers.*.free_text_answer' => ['nullable', 'string'],
        ]);

        $attempt = $this->grading->submit($attempt, $data['answers']);

        return response()->json(['data' => $attempt]);
    }

    public function show(Request $request, string $attemptId)
    {
        $attempt = QuizAttempt::where('user_id', $request->user()->id)
            ->with('answers.question.options')
            ->findOrFail($attemptId);

        return response()->json(['data' => $attempt]);
    }
}
