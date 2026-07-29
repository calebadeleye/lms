<?php

namespace App\Domain\Commerce\Providers;

use App\Domain\Commerce\Contracts\PaymentProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackPaymentProvider implements PaymentProviderInterface
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(private readonly string $secretKey) {}

    public function initializePayment(array $params): array
    {
        $payload = [
            'email' => $params['email'],
            // Paystack amounts are in kobo (the base currency's smallest
            // unit) — our own price_cents columns are already in that unit,
            // so no conversion needed for NGN.
            'amount' => $params['amount_cents'],
            'currency' => $params['currency'],
            'reference' => $params['reference'],
            'callback_url' => $params['callback_url'],
        ];

        if (! empty($params['subaccount_code'])) {
            $payload['subaccount'] = $params['subaccount_code'];
            $payload['bearer'] = 'account'; // subaccount bears the provider fee by default
        }

        $response = $this->client()->post('/transaction/initialize', $payload)->throw()->json();

        return [
            'authorization_url' => $response['data']['authorization_url'],
            'provider_reference' => $response['data']['reference'],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = $this->client()->get("/transaction/verify/{$reference}")->throw()->json();

        return $this->mapTransaction($response['data']);
    }

    public function createSubaccount(array $params): array
    {
        $response = $this->client()->post('/subaccount', [
            'business_name' => $params['business_name'],
            'settlement_bank' => $params['settlement_bank_code'],
            'account_number' => $params['account_number'],
            'percentage_charge' => $params['percentage_charge'],
        ])->throw()->json();

        return ['subaccount_code' => $response['data']['subaccount_code']];
    }

    public function createSubscription(array $params): array
    {
        $response = $this->client()->post('/subscription', [
            'customer' => $params['customer_email'],
            'plan' => $params['plan_code'],
            'authorization' => $params['authorization_code'] ?? null,
        ])->throw()->json();

        return ['subscription_code' => $response['data']['subscription_code']];
    }

    public function cancelSubscription(string $subscriptionCode): bool
    {
        $response = $this->client()->post('/subscription/disable', [
            'code' => $subscriptionCode,
            'token' => $subscriptionCode,
        ]);

        return $response->successful();
    }

    public function refundPayment(string $reference, ?int $amountCents = null): array
    {
        $payload = ['transaction' => $reference];
        if ($amountCents !== null) {
            $payload['amount'] = $amountCents;
        }

        $response = $this->client()->post('/refund', $payload)->throw()->json();

        return [
            'status' => $response['data']['status'] ?? 'pending',
            'provider_reference' => (string) ($response['data']['transaction']['reference'] ?? $reference),
            'amount_cents' => $response['data']['amount'] ?? ($amountCents ?? 0),
        ];
    }

    public function verifyWebhook(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Paystack-Signature');

        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
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
            return $this->client()->get('/balance')->successful();
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return false;
        }
    }

    private function mapTransaction(array $data): array
    {
        return [
            'status' => $data['status'] === 'success' ? 'success' : 'failed',
            'amount_cents' => $data['amount'],
            'currency' => $data['currency'],
            'provider_reference' => $data['reference'],
            'provider_fee_cents' => $data['fees'] ?? 0,
            'paid_at' => $data['paid_at'] ?? null,
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
