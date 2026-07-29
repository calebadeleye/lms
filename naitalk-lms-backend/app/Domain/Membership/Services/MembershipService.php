<?php

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Domain\Membership\Models\LearnerSubscription;
use App\Models\User;

/**
 * The only place a learner_subscriptions row is created or renewed.
 * `MembershipGateService` (this file's sibling) is what actually decides
 * whether membership-only content is unlocked — this class only manages
 * the subscription lifecycle itself.
 */
class MembershipService
{
    public function activate(User $user, LearnerMembershipPlan $plan): LearnerSubscription
    {
        $periodEnd = match ($plan->billing_period) {
            'monthly' => now()->addMonth(),
            'annual' => now()->addYear(),
            default => null, // free plans don't expire
        };

        return LearnerSubscription::updateOrCreate(
            ['user_id' => $user->id, 'plan_id' => $plan->id],
            [
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
            ]
        );
    }

    public function cancel(LearnerSubscription $subscription, bool $atPeriodEnd = true): void
    {
        if ($atPeriodEnd) {
            $subscription->update(['cancel_at_period_end' => true]);

            return;
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function hasActiveMembership(User $user): bool
    {
        return LearnerSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>', now()))
            ->exists();
    }
}
