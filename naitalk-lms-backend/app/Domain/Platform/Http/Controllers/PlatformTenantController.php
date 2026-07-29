<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Billing\Models\PlatformPlan;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning,
        private AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        $tenants = Tenant::query()
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->with('domains', 'subscriptions.plan')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $tenants->items(),
            'meta' => ['pagination' => [
                'page' => $tenants->currentPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ]],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['nullable', 'exists:platform_plans,id'],
            'subscription_status' => ['nullable', 'in:trialing,active,complimentary'],
        ]);

        $plan = isset($data['plan_id']) ? PlatformPlan::find($data['plan_id']) : null;

        $tenant = $this->provisioning->provision(
            name: $data['name'],
            ownerEmail: $data['owner_email'] ?? null,
            ownerName: $data['owner_name'] ?? null,
            plan: $plan,
            subscriptionStatus: $data['subscription_status'] ?? 'trialing',
            invitedByUserId: $request->user()->id,
        );

        $this->auditLogger->log('tenant.created', tenantId: $tenant->id, metadata: ['name' => $tenant->name]);

        return response()->json(['data' => $tenant->load('domains', 'subscriptions.plan')], 201);
    }

    public function show(Tenant $tenant)
    {
        return response()->json(['data' => $tenant->load('domains', 'branding', 'subscriptions.plan', 'owner')]);
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $tenant->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'],
        ]);

        $this->auditLogger->log('tenant.suspended', tenantId: $tenant->id, metadata: ['reason' => $data['reason']]);

        return response()->json(['data' => $tenant->fresh()]);
    }

    public function reactivate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active', 'suspended_at' => null, 'suspension_reason' => null]);

        $this->auditLogger->log('tenant.reactivated', tenantId: $tenant->id);

        return response()->json(['data' => $tenant->fresh()]);
    }

    public function scheduleDeletion(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['retention_days' => ['nullable', 'integer', 'min:1', 'max:365']]);

        $tenant->update([
            'status' => 'deletion_scheduled',
            'deletion_scheduled_at' => now()->addDays($data['retention_days'] ?? 30),
        ]);

        $this->auditLogger->log('tenant.deletion_scheduled', tenantId: $tenant->id, metadata: [
            'scheduled_for' => $tenant->deletion_scheduled_at,
        ]);

        return response()->json(['data' => $tenant->fresh()]);
    }

    /** Undoes a scheduled deletion within the grace period — the scheduled
     * command that actually deletes only ever acts on status =
     * deletion_scheduled, so clearing it back to active is sufficient. */
    public function cancelDeletion(Tenant $tenant)
    {
        $tenant->update(['status' => 'active', 'deletion_scheduled_at' => null]);

        $this->auditLogger->log('tenant.deletion_cancelled', tenantId: $tenant->id);

        return response()->json(['data' => $tenant->fresh()]);
    }
}
