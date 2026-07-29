<?php

namespace App\Domain\Billing\Http\Controllers;

use App\Domain\Billing\Services\TenantUsageService;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;

/**
 * The tenant-side counterpart to PlatformSubscriptionController — what a
 * tenant's own staff can see about their plan and how close they are to its
 * limits. Read-only: changing plans is a platform-staff action
 * (PlatformSubscriptionController), not self-service yet.
 */
class TenantSubscriptionController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantUsageService $usage,
    ) {}

    public function show()
    {
        $tenant = $this->tenantContext->tenant();
        $subscription = $tenant->subscriptions()->with('plan')->latest()->first();

        return response()->json(['data' => [
            'plan' => $subscription?->plan ? [
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'billing_period' => $subscription->plan->billing_period,
                'price_cents' => $subscription->plan->price_cents,
                'currency' => $subscription->plan->currency,
            ] : null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end,
                'complimentary_until' => $subscription->complimentary_until,
            ] : null,
            'usage' => $this->usage->summary($tenant),
        ]]);
    }
}
