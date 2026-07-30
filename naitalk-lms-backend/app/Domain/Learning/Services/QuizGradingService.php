<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Learning\Models\QuizAnswer;
use App\Domain\Learning\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Auto-grades multiple_choice/multiple_answer/true_false questions exactly.
 * `free_text` questions are NOT auto-graded — they're recorded with
 * `is_correct = null` and excluded from both the points-earned and
 * points-possible totals, so an ungraded free-text question neither helps
 * nor hurts the score. Manual grading of free-text answers (an instructor
 * reviewing and setting points_awarded) is documented remaining work, not
 * implemented this phase — anyone building free-text quizzes today should
 * know those questions don't currently affect pass/fail.
 */
class QuizGradingService
{
    public function __construct(private ProgressService $progress) {}

    public function startAttempt(User $user, Enrolment $enrolment, Quiz $quiz): QuizAttempt
    {
        $previousAttempts = QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $user->id)->count();

        if ($quiz->max_attempts && $previousAttempts >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'quiz' => ["You've used all {$quiz->max_attempts} attempts for this quiz."],
            ]);
        }

        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'enrolment_id' => $enrolment->id,
            'attempt_number' => $previousAttempts + 1,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{question_id: int, selected_option_ids?: array<int>, free_text_answer?: string}>  $answers
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            throw new \LogicException('This attempt has already been submitted.');
        }

        $quiz = $attempt->quiz()->with('questions.options')->firstOrFail();
        $answersByQuestion = collect($answers)->keyBy('question_id');

        $earnedPoints = 0;
        $possiblePoints = 0;

        foreach ($quiz->questions as $question) {
            $submitted = $answersByQuestion->get($question->id, []);
            $selectedIds = collect($submitted['selected_option_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values();
            $freeText = $submitted['free_text_answer'] ?? null;

            if (! $question->isAutoGradable()) {
                QuizAnswer::updateOrCreate(
                    ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                    ['selected_option_ids' => null, 'free_text_answer' => $freeText, 'is_correct' => null, 'points_awarded' => 0]
                );

                continue;
            }

            $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values();
            $isCorrect = $selectedIds->all() === $correctIds->all();
            $pointsAwarded = $isCorrect ? $question->points : 0;

            $possiblePoints += $question->points;
            $earnedPoints += $pointsAwarded;

            QuizAnswer::updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                [
                    'selected_option_ids' => $selectedIds->all(),
                    'free_text_answer' => null,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $pointsAwarded,
                ]
            );
        }

        $scorePercent = $possiblePoints > 0 ? (int) round(($earnedPoints / $possiblePoints) * 100) : 0;
        $passed = $scorePercent >= $quiz->passing_score_percent;

        $attempt->update(['submitted_at' => now(), 'score_percent' => $scorePercent, 'passed' => $passed]);

        if ($passed) {
            $this->progress->markCompleteBySystem($attempt->enrolment, $quiz->lesson);
        }

        return $attempt->fresh('answers');
    }
}
