<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\PaymentConfig;

/**
 * The only place commission math happens. Managed-mode transactions owe
 * NAI TALK `commission_percent` (a per-config override, or
 * DEFAULT_MANAGED_COMMISSION_PERCENT — 1% — if unset) of the gross amount.
 * Client-owned transactions never owe a commission at all — the
 * organization's own gateway account is what gets charged, NAI TALK never
 * touches the money.
 */
class CommissionService
{
    /**
     * @return array{commission_cents: int, org_net_cents: int}
     */
    public function calculate(PaymentConfig $config, int $grossAmountCents, int $providerFeeCents = 0): array
    {
        if (! $config->isManaged()) {
            return ['commission_cents' => 0, 'org_net_cents' => $grossAmountCents - $providerFeeCents];
        }

        $commissionCents = (int) round($grossAmountCents * ($config->effectiveCommissionPercent() / 100));
        $orgNetCents = $grossAmountCents - $commissionCents - $providerFeeCents;

        return ['commission_cents' => $commissionCents, 'org_net_cents' => max(0, $orgNetCents)];
    }
}
