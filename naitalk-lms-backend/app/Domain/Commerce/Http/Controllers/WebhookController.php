<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Jobs\ProcessPaymentWebhookJob;
use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Commerce\Models\WebhookEvent;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Public — no `tenant` or `auth` middleware. The webhook comes directly
 * from Paystack/Flutterwave's servers, never through a tenant hostname, so
 * the tenant is resolved from `$webhookToken` instead. See
 * ARCHITECTURE.md §11 for the full verify → idempotency → queue flow.
 */
class WebhookController extends Controller
{
    public function __construct(private PaymentProviderFactory $providers) {}

    public function handle(Request $request, string $provider, string $webhookToken)
    {
        $config = TenantPaymentConfig::withoutTenancy(fn () => TenantPaymentConfig::where('webhook_token', $webhookToken)
            ->where('provider', $provider)
            ->first()
        );

        // Never reveal whether a token is valid to an unauthenticated
        // caller — same response shape either way.
        if (! $config) {
            return response()->json(['received' => true], 200);
        }

        $providerImpl = $this->providers->forTenantConfig($config);
        $secret = $this->verificationSecret($config);

        if (! $secret || ! $providerImpl->verifyWebhook($request, $secret)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = $request->json()->all();
        $eventId = $payload['data']['id'] ?? $payload['data']['reference'] ?? $payload['data']['tx_ref'] ?? null;

        if (! $eventId) {
            return response()->json(['received' => true], 200);
        }

        // Idempotency: a duplicate delivery of the same event short-circuits
        // here via the unique(provider, provider_event_id) index — no job
        // is dispatched a second time.
        $event = WebhookEvent::firstOrCreate(
            ['provider' => $provider, 'provider_event_id' => (string) $eventId],
            [
                'tenant_id' => $config->tenant_id,
                'event_type' => $payload['event'] ?? $payload['type'] ?? null,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($event->wasRecentlyCreated) {
            ProcessPaymentWebhookJob::dispatch($config->tenant_id, $event->id);
        }

        return response()->json(['received' => true], 200);
    }

    /**
     * Paystack signs webhooks with the same secret key used for API calls —
     * it has no separate webhook secret. Flutterwave signs with a
     * dashboard-configured "secret hash" (the `verif-hash` header),
     * independent of its API secret key. In managed mode either value is
     * NAI TALK's own platform credential, never one a tenant could have
     * supplied.
     */
    private function verificationSecret(TenantPaymentConfig $config): string
    {
        return match ($config->provider) {
            'paystack' => $config->isManaged()
                ? (string) config('services.paystack.secret_key')
                : $config->getSecretKey(),
            'flutterwave' => $config->isManaged()
                ? (string) config('services.flutterwave.webhook_secret')
                : $config->getWebhookSecret(),
            default => '',
        };
    }
}
