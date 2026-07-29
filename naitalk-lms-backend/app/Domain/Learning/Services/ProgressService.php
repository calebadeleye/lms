<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;
use App\Domain\Learning\Models\LessonProgress;

/**
 * The only place lesson_progress rows are written. Nothing here trusts the
 * client beyond "the user watched to second N" — completion itself is
 * always decided server-side:
 *
 * - video/audio lessons: complete automatically once the (server-clamped)
 *   playback position reaches 90% of the lesson's known duration.
 * - rich_text/file/external_link/live lessons: no measurable progress
 *   exists, so an explicit "mark complete" action is accepted at face
 *   value — there's nothing to falsify here beyond "I opened this page."
 * - quiz/assignment lessons: completion is NEVER set here. It's a side
 *   effect of QuizGradingService (passing attempt) and
 *   AssignmentSubmissionController (submission recorded) respectively —
 *   calling markComplete() on one of these lesson types is a programming
 *   error, not a valid client action.
 */
class ProgressService
{
    public function __construct(private CourseCompletionService $completion) {}

    public function updatePlaybackPosition(Enrolment $enrolment, Lesson $lesson, int $rawPositionSeconds): LessonProgress
    {
        $clamped = max(0, $rawPositionSeconds);

        if ($lesson->duration_seconds) {
            $clamped = min($clamped, $lesson->duration_seconds);
        }

        $progress = LessonProgress::firstOrCreate(
            ['enrolment_id' => $enrolment->id, 'lesson_id' => $lesson->id],
            ['status' => 'not_started', 'video_position_seconds' => 0]
        );

        // Position only ever moves forward — a client re-sending an earlier
        // timestamp (e.g. a stale tab) can't regress recorded progress.
        $furthest = max($progress->video_position_seconds, $clamped);

        $isNowComplete = $lesson->duration_seconds
            && $furthest >= (int) ($lesson->duration_seconds * 0.9);

        $progress->update([
            'video_position_seconds' => $furthest,
            'status' => $isNowComplete ? 'completed' : 'in_progress',
            'completed_at' => $isNowComplete ? ($progress->completed_at ?? now()) : null,
        ]);

        if ($isNowComplete) {
            $this->completion->recompute($enrolment);
        }

        return $progress->fresh();
    }

    public function markComplete(Enrolment $enrolment, Lesson $lesson): LessonProgress
    {
        if (in_array($lesson->type, ['video', 'audio', 'quiz', 'assignment'], true)) {
            throw new \LogicException("Lesson type '{$lesson->type}' cannot be marked complete directly.");
        }

        $progress = LessonProgress::updateOrCreate(
            ['enrolment_id' => $enrolment->id, 'lesson_id' => $lesson->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        $this->completion->recompute($enrolment);

        return $progress;
    }

    /** Called by QuizGradingService (on pass) and assignment submission —
     * never exposed as a direct student-facing endpoint. */
    public function markCompleteBySystem(Enrolment $enrolment, Lesson $lesson): LessonProgress
    {
        $progress = LessonProgress::updateOrCreate(
            ['enrolment_id' => $enrolment->id, 'lesson_id' => $lesson->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        $this->completion->recompute($enrolment);

        return $progress;
    }
}
