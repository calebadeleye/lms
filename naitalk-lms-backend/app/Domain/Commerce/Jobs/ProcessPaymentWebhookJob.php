<?php

namespace App\Domain\Commerce\Jobs;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Commerce\Models\WebhookEvent;
use App\Domain\Commerce\Services\CommissionService;
use App\Domain\Commerce\Services\OrderFulfillmentService;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Domain\Tenancy\Concerns\TenantAwareJob;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Never trusts the webhook payload's amount or status by itself — always
 * re-verifies against the provider's own API before touching an Order.
 * Order::markPaid()'s guarded status transition is what makes this safe to
 * run twice for the same event (belt-and-braces alongside the
 * webhook_events unique index that should already have caught a duplicate
 * delivery before this job was even dispatched).
 */
class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAwareJob;

    public function __construct(
        public string $tenantId,
        public int $webhookEventId,
    ) {}

    public function handle(
        PaymentProviderFactory $providers,
        CommissionService $commission,
        OrderFulfillmentService $fulfillment,
    ): void {
        $this->establishTenantContext();

        $event = WebhookEvent::findOrFail($this->webhookEventId);
        $config = TenantPaymentConfig::where('tenant_id', $this->tenantId)->firstOrFail();

        try {
            $providerReference = $this->extractReference($event);

            if (! $providerReference) {
                $event->markFailed('Could not extract a transaction reference from the webhook payload.');

                return;
            }

            $provider = $providers->forTenantConfig($config);
            $verified = $provider->verifyPayment($providerReference);

            // Tenant context is already established above, so the normal
            // tenant-scoped query is correctly and automatically filtered.
            $order = Order::where('provider_reference', $providerReference)->first();

            if (! $order) {
                $event->markFailed("No matching order for provider_reference={$providerReference}.");

                return;
            }

            if ($verified['status'] !== 'success') {
                $order->update(['status' => 'failed']);
                $event->markProcessed();

                return;
            }

            if ($order->isPaid()) {
                // Already fulfilled by an earlier delivery of this (or an
                // equivalent) event — acknowledge without redoing anything.
                $event->markProcessed();

                return;
            }

            $split = $commission->calculate($config, $verified['amount_cents'], $verified['provider_fee_cents']);

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $config->provider,
                'provider_reference' => $verified['provider_reference'],
                'status' => 'success',
                'gross_amount_cents' => $verified['amount_cents'],
                'currency' => $verified['currency'],
                'provider_fee_cents' => $verified['provider_fee_cents'],
                'platform_commission_cents' => $split['commission_cents'],
                'tenant_net_cents' => $split['tenant_net_cents'],
                'fee_bearer' => $config->fee_bearer,
                'paid_at' => now(),
                'raw_response' => $this->stripSecrets($verified['raw']),
            ]);

            $order->markPaid();
            $fulfillment->fulfill($order, $payment);

            $event->markProcessed();
        } catch (\Throwable $e) {
            $event->markFailed($e->getMessage());

            throw $e;
        }
    }

    private function extractReference(WebhookEvent $event): ?string
    {
        $payload = $event->payload;

        return $payload['data']['reference'] ?? $payload['data']['tx_ref'] ?? null;
    }

    private function stripSecrets(array $raw): array
    {
        unset($raw['authorization'], $raw['card'], $raw['customer']['phone']);

        return $raw;
    }
}
