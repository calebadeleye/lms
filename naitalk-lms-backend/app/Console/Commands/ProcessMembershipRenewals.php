<?php

namespace App\Console\Commands;

use App\Domain\Membership\Models\LearnerSubscription;
use Illuminate\Console\Command;

/**
 * Moves learner_subscriptions through active -> past_due -> expired as
 * their current_period_end passes. Real auto-renewal (charging a stored
 * card/authorization automatically) is NOT implemented — Paystack and
 * Flutterwave don't share a common "charge this saved authorization"
 * primitive cleanly enough to build one true implementation across both in
 * Phase 3's scope (see ARCHITECTURE.md §11's note on createSubscription()).
 * A past_due subscription is a real, visible state a learner sees and can
 * act on (renew manually via a fresh checkout), not a silently-lost one.
 */
class ProcessMembershipRenewals extends Command
{
    protected $signature = 'memberships:process-renewals {--grace-days=7}';

    protected $description = 'Transition expired learner memberships through past_due to expired.';

    public function handle(): int
    {
        $graceDays = (int) $this->option('grace-days');

        $expiring = LearnerSubscription::withoutTenancy(fn () => LearnerSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get()
        );

        foreach ($expiring as $subscription) {
            if ($subscription->cancel_at_period_end) {
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $this->line("Subscription {$subscription->id}: active -> cancelled (requested)");
            } else {
                $subscription->update(['status' => 'past_due']);
                $this->line("Subscription {$subscription->id}: active -> past_due");
            }
        }

        $overdue = LearnerSubscription::withoutTenancy(fn () => LearnerSubscription::query()
            ->where('status', 'past_due')
            ->where('current_period_end', '<', now()->subDays($graceDays))
            ->get()
        );

        foreach ($overdue as $subscription) {
            $subscription->update(['status' => 'expired']);
            $this->line("Subscription {$subscription->id}: past_due -> expired");
        }

        $this->info(sprintf('Processed %d expirations, %d grace-period expirations.', $expiring->count(), $overdue->count()));

        return self::SUCCESS;
    }
}
