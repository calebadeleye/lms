<?php

namespace App\Domain\Membership\Services;

use App\Domain\Learning\Models\Course;
use App\Models\User;

/**
 * The single call site for "does this user's membership unlock this
 * content." A course with pricing_type = membership_only is unlocked by
 * ANY active membership — not tied to a specific plan tier. See
 * ARCHITECTURE.md §9 for why per-plan restriction isn't implemented.
 */
class MembershipGateService
{
    public function __construct(private MembershipService $memberships) {}

    public function canAccessCourse(User $user, Course $course): bool
    {
        if ($course->pricing_type !== 'membership_only') {
            return true;
        }

        return $this->memberships->hasActiveMembership($user);
    }
}
