<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Exceptions\CourseNotFreeException;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Models\User;

/**
 * The only place an Enrolment row is created.
 *
 * Deliberately does not know about memberships or payments — 'source' is
 * an audit trail of *how* access was granted, and it's the caller's job to
 * have already verified eligibility for that source before calling this
 * (CourseController::enrol for free/membership, OrderFulfillmentService for
 * paid — see ARCHITECTURE.md §11). Keeping this decoupled from
 * App\Domain\Membership avoids a circular cross-domain dependency.
 */
class EnrolmentService
{
    public function enroll(User $user, Course $course, string $source = 'free'): Enrolment
    {
        if ($source === 'free' && $course->pricing_type !== 'free') {
            throw new CourseNotFreeException;
        }

        return Enrolment::firstOrCreate(
            ['course_id' => $course->id, 'user_id' => $user->id],
            ['status' => 'active', 'source' => $source, 'enrolled_at' => now()]
        );
    }

    public function isEnrolled(User $user, Course $course): bool
    {
        return Enrolment::where('course_id', $course->id)->where('user_id', $user->id)->exists();
    }
}
