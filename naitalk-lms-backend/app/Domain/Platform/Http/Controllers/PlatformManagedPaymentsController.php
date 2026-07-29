<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Http\Controllers\Controller;

/**
 * Oversight of every tenant on NAI TALK's own managed payment gateway
 * (TenantPaymentConfig::mode === 'managed') — the subaccount NAI TALK's own
 * Paystack/Flutterwave account created for them, and the commission rate
 * being applied. Tenants on client-owned gateways never appear here; NAI
 * TALK holds no credentials or commission stake in that mode.
 */
class PlatformManagedPaymentsController extends Controller
{
    public function index()
    {
        $configs = TenantPaymentConfig::withoutTenancy(fn () => TenantPaymentConfig::with('tenant:id,name,slug')
            ->where('mode', 'managed')
            ->orderByDesc('created_at')
            ->get()
        );

        return response()->json(['data' => $configs->map(fn (TenantPaymentConfig $config) => [
            ...$config->toArray(),
            'effective_commission_percent' => $config->effectiveCommissionPercent(),
        ])]);
    }
}
