<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Billing\Models\TenantSubscription;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlatformSubscriptionController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function show(Tenant $tenant)
    {
        $subscription = TenantSubscription::withoutTenancy(fn () => TenantSubscription::where('tenant_id', $tenant->id)
            ->with('plan.features')
            ->latest()
            ->first()
        );

        return response()->json(['data' => $subscription]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:platform_plans,id'],
            'status' => ['required', 'in:trialing,active,grace_period,past_due,suspended,cancelled,complimentary'],
            'current_period_end' => ['nullable', 'date'],
        ]);

        $subscription = TenantSubscription::withoutTenancy(fn () => TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data + ['current_period_start' => now()]
        ));

        $this->auditLogger->log('subscription.updated', tenantId: $tenant->id, metadata: $data);

        return response()->json(['data' => $subscription->fresh('plan')]);
    }

    /**
     * Grant permanent or time-limited complimentary access without ever
     * creating a paid-invoice fiction.
     */
    public function grantComplimentary(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:platform_plans,id'],
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date', 'after:today'],
        ]);

        $staff = $request->attributes->get('platform_staff');

        $subscription = TenantSubscription::withoutTenancy(fn () => TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $data['plan_id'],
                'status' => 'complimentary',
                'current_period_start' => now(),
                'current_period_end' => null,
                'complimentary_until' => $data['until'] ?? null,
                'complimentary_reason' => $data['reason'],
                'granted_by_platform_staff_id' => $staff?->id,
            ]
        ));

        $this->auditLogger->log('subscription.complimentary_granted', tenantId: $tenant->id, metadata: [
            'reason' => $data['reason'], 'until' => $data['until'] ?? null,
        ]);

        return response()->json(['data' => $subscription->fresh('plan')]);
    }
}
