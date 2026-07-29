<?php

namespace App\Domain\Membership\Services;

use App\Domain\Learning\Models\Course;
use App\Models\User;

/**
 * The single call site for "does this user's membership unlock this
 * content." A course with pricing_type = membership_only, or a
 * member-only community channel, is unlocked by ANY active membership in
 * the tenant — not tied to a specific plan tier. See ARCHITECTURE.md §11
 * for why per-plan restriction isn't implemented.
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
