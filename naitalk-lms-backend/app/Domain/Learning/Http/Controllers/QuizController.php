<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Learning\Models\QuizAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    /**
     * Admin builder — creates or replaces the quiz (and all its questions
     * and options) for a lesson in one request, since that's how a course
     * builder UI naturally edits a quiz. Includes correct answers.
     */
    public function save(Request $request, string $lessonId)
    {
        $lesson = Lesson::findOrFail($lessonId);

        $data = $request->validate([
            'passing_score_percent' => ['integer', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'randomize_questions' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.type' => ['required', 'in:multiple_choice,multiple_answer,true_false,free_text'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.points' => ['integer', 'min:1'],
            'questions.*.options' => ['required_unless:questions.*.type,free_text', 'array'],
            'questions.*.options.*.option_text' => ['required_with:questions.*.options', 'string'],
            'questions.*.options.*.is_correct' => ['boolean'],
        ]);

        $quiz = DB::transaction(function () use ($lesson, $data) {
            $quiz = Quiz::updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'passing_score_percent' => $data['passing_score_percent'] ?? 70,
                    'max_attempts' => $data['max_attempts'] ?? null,
                    'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                    'randomize_questions' => $data['randomize_questions'] ?? false,
                ]
            );

            $quiz->questions()->delete();

            foreach ($data['questions'] as $index => $questionData) {
                $question = $quiz->questions()->create([
                    'type' => $questionData['type'],
                    'question_text' => $questionData['question_text'],
                    'points' => $questionData['points'] ?? 1,
                    'sort_order' => $index,
                ]);

                foreach ($questionData['options'] ?? [] as $optionIndex => $optionData) {
                    $question->options()->create([
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'] ?? false,
                        'sort_order' => $optionIndex,
                    ]);
                }
            }

            return $quiz;
        });

        return response()->json(['data' => $quiz->fresh('questions.options')], 201);
    }

    public function adminShow(string $lessonId)
    {
        $quiz = Quiz::where('lesson_id', $lessonId)->with('questions.options')->firstOrFail();

        return response()->json(['data' => $quiz]);
    }

    /** Student-facing — never exposes is_correct. */
    public function show(Request $request, string $lessonId)
    {
        $quiz = Quiz::where('lesson_id', $lessonId)->with('questions.options')->firstOrFail();
        $user = $request->user();

        $attemptsUsed = QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $user->id)->count();
        $canAttempt = ! $quiz->max_attempts || $attemptsUsed < $quiz->max_attempts;

        return response()->json(['data' => [
            'id' => $quiz->id,
            'passing_score_percent' => $quiz->passing_score_percent,
            'max_attempts' => $quiz->max_attempts,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'attempts_used' => $attemptsUsed,
            'can_attempt' => $canAttempt,
            'questions' => $quiz->questions->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'question_text' => $q->question_text,
                'points' => $q->points,
                'options' => $q->options->map->only(['id', 'option_text']),
            ]),
        ]]);
    }
}
