<?php

namespace App\Domain\Commerce\Providers;

use App\Domain\Commerce\Contracts\PaymentProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FlutterwavePaymentProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct(private readonly string $secretKey) {}

    public function initializePayment(array $params): array
    {
        $payload = [
            'tx_ref' => $params['reference'],
            // Flutterwave amounts are major units (naira, not kobo) —
            // unlike Paystack. Converted here so callers always pass cents.
            'amount' => $params['amount_cents'] / 100,
            'currency' => $params['currency'],
            'redirect_url' => $params['callback_url'],
            'customer' => ['email' => $params['email']],
        ];

        if (! empty($params['subaccount_code'])) {
            $payload['subaccounts'] = [['id' => $params['subaccount_code']]];
        }

        $response = $this->client()->post('/payments', $payload)->throw()->json();

        return [
            'authorization_url' => $response['data']['link'],
            'provider_reference' => $params['reference'],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = $this->client()
            ->get('/transactions/verify_by_reference', ['tx_ref' => $reference])
            ->throw()->json();

        return $this->mapTransaction($response['data']);
    }

    public function createSubaccount(array $params): array
    {
        $response = $this->client()->post('/subaccounts', [
            'account_bank' => $params['settlement_bank_code'],
            'account_number' => $params['account_number'],
            'business_name' => $params['business_name'],
            'split_type' => 'percentage',
            'split_value' => $params['percentage_charge'] / 100,
        ])->throw()->json();

        return ['subaccount_code' => (string) $response['data']['id']];
    }

    /**
     * Flutterwave has no direct equivalent of Paystack's "charge an existing
     * authorization on a plan" subscription object — its nearest primitive
     * is a Payment Plan that a customer opts into at checkout time. This
     * creates that plan; actually enrolling a customer happens by passing
     * `payment_plan` on a subsequent initializePayment() call, not here.
     * Documented gap, not a silent behavioural difference.
     */
    public function createSubscription(array $params): array
    {
        $response = $this->client()->post('/payment-plans', [
            'amount' => $params['amount_cents'] ?? 0,
            'name' => $params['plan_code'],
            'interval' => $params['interval'] ?? 'monthly',
        ])->throw()->json();

        return ['subscription_code' => (string) $response['data']['id']];
    }

    public function cancelSubscription(string $subscriptionCode): bool
    {
        $response = $this->client()->put("/payment-plans/{$subscriptionCode}/cancel");

        return $response->successful();
    }

    public function refundPayment(string $reference, ?int $amountCents = null): array
    {
        $transaction = $this->verifyPayment($reference);
        $transactionId = $transaction['raw']['id'];

        $payload = [];
        if ($amountCents !== null) {
            $payload['amount'] = $amountCents / 100;
        }

        $response = $this->client()->post("/transactions/{$transactionId}/refund", $payload)->throw()->json();

        return [
            'status' => ($response['data']['status'] ?? '') === 'completed' ? 'processed' : 'pending',
            'provider_reference' => $reference,
            'amount_cents' => (int) round(($response['data']['amount_refunded'] ?? ($amountCents ?? 0) / 100) * 100),
        ];
    }

    public function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = $request->header('verif-hash');

        if (! $signature) {
            return false;
        }

        return hash_equals($secret, $signature);
    }

    public function fetchTransaction(string $reference): array
    {
        return $this->verifyPayment($reference);
    }

    public function fetchSettlement(string $reference): array
    {
        // Not implemented — see PaymentProviderInterface::fetchSettlement().
        return ['settled' => false, 'settlement_date' => null, 'raw' => []];
    }

    public function testConnection(): bool
    {
        try {
            return $this->client()->get('/balances')->successful();
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return false;
        }
    }

    private function mapTransaction(array $data): array
    {
        return [
            'status' => $data['status'] === 'successful' ? 'success' : 'failed',
            'amount_cents' => (int) round($data['amount'] * 100),
            'currency' => $data['currency'],
            'provider_reference' => $data['tx_ref'],
            'provider_fee_cents' => (int) round(($data['app_fee'] ?? 0) * 100),
            'paid_at' => $data['created_at'] ?? null,
            'raw' => $data,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($this->secretKey)
            ->acceptJson();
    }
}
