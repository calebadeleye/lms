<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Commerce\Jobs\ProcessPaymentWebhookJob;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Commerce\Models\WebhookEvent;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Public — no auth. The webhook comes directly from Paystack/Flutterwave's
 * servers, addressed by `$webhookToken` rather than any session. See
 * ARCHITECTURE.md §11 for the full verify → idempotency → queue flow.
 */
class WebhookController extends Controller
{
    public function __construct(private PaymentProviderFactory $providers) {}

    public function handle(Request $request, string $provider, string $webhookToken)
    {
        $config = PaymentConfig::where('webhook_token', $webhookToken)
            ->where('provider', $provider)
            ->first();

        // Never reveal whether a token is valid to an unauthenticated
        // caller — same response shape either way.
        if (! $config) {
            return response()->json(['received' => true], 200);
        }

        $providerImpl = $this->providers->forConfig($config);
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
                'event_type' => $payload['event'] ?? $payload['type'] ?? null,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($event->wasRecentlyCreated) {
            ProcessPaymentWebhookJob::dispatch($event->id);
        }

        return response()->json(['received' => true], 200);
    }

    /**
     * Paystack signs webhooks with the same secret key used for API calls —
     * it has no separate webhook secret. Flutterwave signs with a
     * dashboard-configured "secret hash" (the `verif-hash` header),
     * independent of its API secret key. In managed mode either value is
     * NAI TALK's own platform credential, never one the organization could
     * have supplied.
     */
    private function verificationSecret(PaymentConfig $config): string
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
