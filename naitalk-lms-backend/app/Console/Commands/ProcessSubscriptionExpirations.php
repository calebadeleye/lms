<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\TenantSubscription;
use Illuminate\Console\Command;

/**
 * Moves subscriptions through active -> grace_period -> suspended as their
 * current_period_end passes. Complimentary subscriptions with no
 * complimentary_until are permanent and never touched here; ones with a
 * complimentary_until in the past fall back to whatever the tenant's plan
 * dictates (grace period first, same as a paid subscription).
 */
class ProcessSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:process-expirations {--grace-days=7}';

    protected $description = 'Transition expired tenant subscriptions through grace period to suspension.';

    public function handle(): int
    {
        $graceDays = (int) $this->option('grace-days');

        $expiredActive = TenantSubscription::withoutTenancy(fn () => TenantSubscription::query()
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->where('current_period_end', '<', now())
            ->get()
        );

        foreach ($expiredActive as $subscription) {
            $subscription->update([
                'status' => 'grace_period',
                'grace_period_ends_at' => now()->addDays($graceDays),
            ]);
            $this->line("Tenant {$subscription->tenant_id}: active -> grace_period");
        }

        $expiredComplimentary = TenantSubscription::withoutTenancy(fn () => TenantSubscription::query()
            ->where('status', 'complimentary')
            ->whereNotNull('complimentary_until')
            ->where('complimentary_until', '<', now())
            ->get()
        );

        foreach ($expiredComplimentary as $subscription) {
            $subscription->update([
                'status' => 'grace_period',
                'grace_period_ends_at' => now()->addDays($graceDays),
            ]);
            $this->line("Tenant {$subscription->tenant_id}: complimentary expired -> grace_period");
        }

        $expiredGrace = TenantSubscription::withoutTenancy(fn () => TenantSubscription::query()
            ->where('status', 'grace_period')
            ->where('grace_period_ends_at', '<', now())
            ->get()
        );

        foreach ($expiredGrace as $subscription) {
            $subscription->update(['status' => 'suspended']);
            $this->line("Tenant {$subscription->tenant_id}: grace_period -> suspended");
        }

        $this->info(sprintf(
            'Processed %d expirations, %d complimentary expirations, %d grace-period suspensions.',
            $expiredActive->count(), $expiredComplimentary->count(), $expiredGrace->count()
        ));

        return self::SUCCESS;
    }
}
