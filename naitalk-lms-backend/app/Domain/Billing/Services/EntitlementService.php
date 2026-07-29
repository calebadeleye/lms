<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\SubscriptionOverride;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * The single call site for "what can this tenant do". Resolution order:
 * unexpired subscription_overrides row -> current plan's plan_features ->
 * hard-coded safe default. Nothing else in the app reads plan_features or
 * subscription_overrides directly.
 */
class EntitlementService
{
    private const DEFAULTS = [
        'max_administrators' => '2',
        'max_instructors' => '3',
        'max_coaches' => '2',
        'max_active_students' => '50',
        'max_published_courses' => '5',
        'storage_gb' => '5',
        'certificates_enabled' => 'false',
        'certificate_template_limit' => '1',
        'coaching_enabled' => 'false',
        'memberships_enabled' => 'false',
        'custom_domain_enabled' => 'false',
        'client_owned_gateway_enabled' => 'false',
        'managed_gateway_enabled' => 'true',
        'full_data_export_enabled' => 'true',
        'api_access_enabled' => 'false',
        'native_mobile_app_enabled' => 'false',
        'advanced_reporting_enabled' => 'false',
    ];

    public function value(Tenant $tenant, string $featureKey): string
    {
        return Cache::remember(
            "tenant:{$tenant->id}:entitlement:{$featureKey}",
            now()->addMinutes(5),
            function () use ($tenant, $featureKey) {
                $override = SubscriptionOverride::withoutTenancy(fn () => SubscriptionOverride::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('feature_key', $featureKey)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->latest()
                    ->first()
                );

                if ($override) {
                    return $override->value;
                }

                $subscription = $tenant->subscriptions()->latest()->first();
                $planFeature = $subscription?->plan?->features->firstWhere('feature_key', $featureKey);

                return $planFeature?->value ?? self::DEFAULTS[$featureKey] ?? 'false';
            }
        );
    }

    public function boolean(Tenant $tenant, string $featureKey): bool
    {
        return filter_var($this->value($tenant, $featureKey), FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(Tenant $tenant, string $featureKey): int
    {
        return (int) $this->value($tenant, $featureKey);
    }

    public function forget(Tenant $tenant, string $featureKey): void
    {
        Cache::forget("tenant:{$tenant->id}:entitlement:{$featureKey}");
    }
}
