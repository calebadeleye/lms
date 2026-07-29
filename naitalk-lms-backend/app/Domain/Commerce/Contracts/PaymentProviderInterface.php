<?php

namespace App\Domain\Commerce\Contracts;

use Illuminate\Http\Request;

/**
 * One interface, two real implementations (Paystack, Flutterwave). No
 * controller or service ever branches on provider name — callers resolve a
 * concrete instance via App\Domain\Commerce\Services\PaymentProviderFactory,
 * which knows how to pick the right credentials for a given tenant + mode.
 */
interface PaymentProviderInterface
{
    /**
     * @param  array{email: string, amount_cents: int, currency: string, reference: string,
     *               callback_url: string, subaccount_code?: string}  $params
     * @return array{authorization_url: string, provider_reference: string}
     */
    public function initializePayment(array $params): array;

    /**
     * @return array{status: string, amount_cents: int, currency: string, provider_reference: string,
     *               provider_fee_cents: int, paid_at: ?string, raw: array}
     */
    public function verifyPayment(string $reference): array;

    /**
     * @param  array{business_name: string, settlement_bank_code: string, account_number: string,
     *               percentage_charge: float}  $params
     * @return array{subaccount_code: string}
     */
    public function createSubaccount(array $params): array;

    /**
     * @param  array{plan_code: string, customer_email: string, authorization_code?: string}  $params
     * @return array{subscription_code: string}
     */
    public function createSubscription(array $params): array;

    public function cancelSubscription(string $subscriptionCode): bool;

    /**
     * @return array{status: string, provider_reference: string, amount_cents: int}
     */
    public function refundPayment(string $reference, ?int $amountCents = null): array;

    /** Verifies the raw request signature against the tenant's configured
     * webhook secret — must run before the payload is trusted at all. */
    public function verifyWebhook(Request $request, string $secret): bool;

    /**
     * @return array{status: string, amount_cents: int, currency: string, provider_reference: string,
     *               provider_fee_cents: int, paid_at: ?string, raw: array}
     */
    public function fetchTransaction(string $reference): array;

    /**
     * Settlement reporting (when funds actually land in the tenant's bank
     * account) is provider- and country-specific in ways that go well
     * beyond Phase 3's acceptance criteria (commission *calculation*, not
     * settlement *reconciliation*). The interface shape is real; both
     * implementations document this as a deliberate gap rather than
     * returning fabricated data.
     *
     * @return array{settled: bool, settlement_date: ?string, raw: array}
     */
    public function fetchSettlement(string $reference): array;

    /** Lightweight authenticated call used to confirm a secret key is valid
     * and reachable — never verifies more than "the provider accepted our
     * credentials," used by the "test connection" action in tenant settings. */
    public function testConnection(): bool;
}
