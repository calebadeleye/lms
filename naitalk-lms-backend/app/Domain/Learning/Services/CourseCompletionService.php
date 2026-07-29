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
        $mandatoryLessonIds = Lesson::query()
            ->whereHas('courseModule', fn ($q) => $q->where('course_id', $enrolment->course_id))
            ->where('is_mandatory', true)
            ->pluck('id');

        if ($mandatoryLessonIds->isEmpty()) {
            return 0;
        }

        $completedCount = $enrolment->progress()
            ->whereIn('lesson_id', $mandatoryLessonIds)
            ->where('status', 'completed')
            ->count();

        $percent = (int) round(($completedCount / $mandatoryLessonIds->count()) * 100);

        if ($percent >= 100 && ! $enrolment->isCompleted()) {
            $enrolment->update(['status' => 'completed', 'completed_at' => now()]);
            $this->certificates->issueForEnrolment($enrolment->fresh());
        }

        return $percent;
    }
}
