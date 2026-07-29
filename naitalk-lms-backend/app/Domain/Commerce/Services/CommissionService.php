<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\TenantPaymentConfig;

/**
 * The only place commission math happens. Managed-mode transactions owe
 * NAI TALK `commission_percent` (tenant override, or
 * DEFAULT_MANAGED_COMMISSION_PERCENT — 1% — if unset) of the gross amount.
 * Client-owned transactions never owe a commission at all — the tenant's
 * own gateway account is what gets charged, NAI TALK never touches the
 * money.
 */
class CommissionService
{
    /**
     * @return array{commission_cents: int, tenant_net_cents: int}
     */
    public function calculate(TenantPaymentConfig $config, int $grossAmountCents, int $providerFeeCents = 0): array
    {
        if (! $config->isManaged()) {
            return ['commission_cents' => 0, 'tenant_net_cents' => $grossAmountCents - $providerFeeCents];
        }

        $commissionCents = (int) round($grossAmountCents * ($config->effectiveCommissionPercent() / 100));
        $tenantNetCents = $grossAmountCents - $commissionCents - $providerFeeCents;

        return ['commission_cents' => $commissionCents, 'tenant_net_cents' => max(0, $tenantNetCents)];
    }
}
