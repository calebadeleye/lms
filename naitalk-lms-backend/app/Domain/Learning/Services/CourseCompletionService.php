<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Lesson;

/**
 * The only place an enrolment gets flipped to `completed`. Percentage is
 * always recomputed from `lesson_progress` against the course's current
 * mandatory lessons — never cached anywhere the client could influence.
 */
class CourseCompletionService
{
    public function __construct(private CertificateService $certificates) {}

    public function recompute(Enrolment $enrolment): int
    {
        // Personality-type videos are mutually exclusive — a learner only
        // ever needs to watch the one matching their selection, so they
        // count as a single slot in the denominator rather than one slot
        // per video. That slot exists (and is unmet) even before a
        // selection is made, so completion can never reach 100% by having
        // the other videos silently excluded from the count.
        $mandatoryLessonIds = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $enrolment->course_id))
            ->where('is_mandatory', true)
            ->whereNull('personality_type_code')
            ->pluck('id');

        $hasPersonalitySlot = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $enrolment->course_id))
            ->where('is_mandatory', true)
            ->whereNotNull('personality_type_code')
            ->exists();

        $totalSlots = $mandatoryLessonIds->count() + ($hasPersonalitySlot ? 1 : 0);

        if ($totalSlots === 0) {
            return 0;
        }

        $completedCount = $enrolment->progress()
            ->whereIn('lesson_id', $mandatoryLessonIds)
            ->where('status', 'completed')
            ->count();

        if ($hasPersonalitySlot && $enrolment->personality_type) {
            $myPersonalityLessonId = Lesson::query()
                ->whereHas('courseModule', fn ($q) => $q->where('course_id', $enrolment->course_id))
                ->where('personality_type_code', $enrolment->personality_type)
                ->value('id');

            if ($myPersonalityLessonId && $enrolment->progress()->where('lesson_id', $myPersonalityLessonId)->where('status', 'completed')->exists()) {
                $completedCount++;
            }
        }

        $percent = (int) round(($completedCount / $totalSlots) * 100);

        if ($percent >= 100 && ! $enrolment->isCompleted()) {
            $enrolment->update(['status' => 'completed', 'completed_at' => now()]);
            $this->certificates->issueForEnrolment($enrolment->fresh());
        }

        return $percent;
    }
}
