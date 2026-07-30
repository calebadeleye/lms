<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\Refund;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Models\User;

class RefundService
{
    public function __construct(private PaymentProviderFactory $providers) {}

    public function refund(Payment $payment, User $processor, ?int $amountCents = null, ?string $reason = null): Refund
    {
        $config = PaymentConfig::where('provider', $payment->provider)->firstOrFail();
        $provider = $this->providers->forConfig($config);

        $result = $provider->refundPayment($payment->provider_reference, $amountCents);

        $refund = Refund::create([
            'payment_id' => $payment->id,
            'amount_cents' => $result['amount_cents'] ?: ($amountCents ?? $payment->gross_amount_cents),
            'reason' => $reason,
            'status' => $result['status'] === 'processed' ? 'processed' : 'pending',
            'provider_reference' => $result['provider_reference'],
            'processed_by' => $processor->id,
            'processed_at' => now(),
        ]);

        $isFullRefund = $refund->amount_cents >= $payment->gross_amount_cents;
        $payment->order->update(['status' => $isFullRefund ? 'refunded' : 'partially_refunded']);

        return $refund;
    }
}
