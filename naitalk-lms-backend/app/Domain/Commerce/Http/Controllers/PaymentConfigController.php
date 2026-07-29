<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Domain\Platform\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentConfigController extends Controller
{
    public function __construct(
        private PaymentProviderFactory $providers,
        private AuditLogger $auditLogger,
    ) {}

    public function show()
    {
        $config = TenantPaymentConfig::first();

        return response()->json(['data' => $this->present($config)]);
    }

    /** Client-owned mode: the tenant's own Paystack/Flutterwave credentials. */
    public function setClientOwned(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:paystack,flutterwave'],
            'public_key' => ['required', 'string'],
            'secret_key' => ['required', 'string'],
            'webhook_secret' => ['required', 'string'],
            'fee_bearer' => ['in:tenant,learner,platform'],
            'environment' => ['in:test,live'],
        ]);

        $config = TenantPaymentConfig::firstOrNew([]);
        $config->fill([
            'provider' => $data['provider'],
            'mode' => 'client_owned',
            'public_key' => $data['public_key'],
            'fee_bearer' => $data['fee_bearer'] ?? 'tenant',
            'environment' => $data['environment'] ?? 'test',
            'status' => 'active',
            // Managed-only fields don't apply here.
            'commission_percent' => null,
            'subaccount_code' => null,
        ]);
        $config->setSecretKey($data['secret_key']);
        $config->setWebhookSecret($data['webhook_secret']);
        $config->save();

        $this->auditLogger->log('payment_config.set_client_owned', metadata: ['provider' => $data['provider']]);

        return response()->json(['data' => $this->present($config)]);
    }

    /**
     * Managed mode: NAI TALK's own gateway. Creates a provider subaccount
     * for split settlement so the tenant's share lands with them directly —
     * NAI TALK's 1% commission is deducted at the provider level via the
     * split, not moved manually afterward.
     */
    public function setManaged(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:paystack,flutterwave'],
            'business_name' => ['required', 'string'],
            'settlement_bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'fee_bearer' => ['in:tenant,learner,platform'],
        ]);

        $config = TenantPaymentConfig::firstOrNew([]);
        $config->fill([
            'provider' => $data['provider'],
            'mode' => 'managed',
            'public_key' => null,
            'fee_bearer' => $data['fee_bearer'] ?? 'tenant',
            'environment' => 'live',
            'status' => 'active',
            'commission_percent' => null, // null = platform default
        ]);
        $config->secret_key_encrypted = null;
        $config->webhook_secret_encrypted = null;
        $config->save();

        $provider = $this->providers->forTenantConfig($config);

        $subaccount = $provider->createSubaccount([
            'business_name' => $data['business_name'],
            'settlement_bank_code' => $data['settlement_bank_code'],
            'account_number' => $data['account_number'],
            'percentage_charge' => $config->effectiveCommissionPercent(),
        ]);

        $config->update(['subaccount_code' => $subaccount['subaccount_code']]);

        $this->auditLogger->log('payment_config.set_managed', metadata: ['provider' => $data['provider']]);

        return response()->json(['data' => $this->present($config->fresh())]);
    }

    public function testConnection()
    {
        $config = TenantPaymentConfig::first();

        if (! $config) {
            throw ValidationException::withMessages(['provider' => ['No payment gateway configured yet.']]);
        }

        $provider = $this->providers->forTenantConfig($config);
        $connected = $provider->testConnection();

        $config->update(['last_verified_at' => $connected ? now() : $config->last_verified_at]);

        return response()->json(['data' => ['connected' => $connected]]);
    }

    private function present(?TenantPaymentConfig $config): ?array
    {
        if (! $config) {
            return null;
        }

        return [
            'provider' => $config->provider,
            'mode' => $config->mode,
            // The public key is not a secret — Paystack/Flutterwave design it
            // to be embedded directly in client-side checkout widgets — so
            // it's safe to send back in full for the settings form to
            // pre-fill. Only the masked version is meant for display; only
            // the secret/webhook keys stay genuinely hidden.
            'public_key' => $config->public_key,
            'public_key_masked' => $config->maskedPublicKey(),
            'has_secret_configured' => $config->hasSecretConfigured(),
            'subaccount_code' => $config->subaccount_code,
            'environment' => $config->environment,
            'commission_percent' => $config->effectiveCommissionPercent(),
            'fee_bearer' => $config->fee_bearer,
            'status' => $config->status,
            'last_verified_at' => $config->last_verified_at,
            'webhook_url' => $config->webhookUrl(),
        ];
    }
}
