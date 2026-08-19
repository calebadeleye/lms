<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\MembershipFeePayment;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Identity\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns "a user wants to buy this sellable thing" into a pending Order plus
 * a provider-hosted checkout URL. Never called for free items — those are
 * fulfilled directly (EnrolmentService, membership activation) without ever
 * touching the commerce tables at all.
 *
 * Price/currency are passed explicitly rather than read off the itemable
 * model, because not every itemable carries its own price — a coaching
 * Booking's price comes from its CoachingService, but the Booking itself
 * (not the service) is what OrderFulfillmentService needs to confirm.
 */
class CheckoutService
{
    public function __construct(private PaymentProviderFactory $providers) {}

    public function checkout(
        User $user,
        Model $itemable,
        string $itemName,
        int $priceCents,
        string $currency,
        string $callbackUrl,
    ): array {
        $config = PaymentConfig::where('status', 'active')->first();

        if (! $config) {
            throw ValidationException::withMessages([
                'payment' => ['This academy has not configured a payment method yet.'],
            ]);
        }

        $estimatedFeeCents = $config->fee_bearer === 'learner'
            ? (int) round($priceCents * (config('services.managed_payments.estimated_provider_fee_percent', 1.5) / 100))
            : 0;
        $totalCents = $priceCents + $estimatedFeeCents;

        $order = DB::transaction(function () use ($user, $itemable, $itemName, $config, $priceCents, $currency, $estimatedFeeCents, $totalCents) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'currency' => $currency,
                'subtotal_cents' => $priceCents,
                'fee_cents' => $estimatedFeeCents,
                'total_cents' => $totalCents,
                'payment_mode' => $config->mode,
                'provider' => $config->provider,
            ]);

            $order->items()->create([
                'itemable_type' => $itemable->getMorphClass(),
                'itemable_id' => $itemable->id,
                'name' => $itemName,
                'unit_price_cents' => $priceCents,
                'quantity' => 1,
            ]);

            return $order;
        });

        $reference = "order_{$order->id}_{$order->idempotency_key}";

        $provider = $this->providers->forConfig($config);

        $result = $provider->initializePayment([
            'email' => $user->email,
            'amount_cents' => $totalCents,
            'currency' => $order->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'subaccount_code' => $config->subaccount_code,
        ]);

        $order->update(['provider_reference' => $result['provider_reference']]);

        return ['authorization_url' => $result['authorization_url'], 'order' => $order->fresh('items')];
    }

    /**
     * The one-time HR GEMs membership registration fee. Deliberately doesn't
     * go through the org's own PaymentConfig at all — this always charges
     * via the platform's own managed Paystack account (see
     * PaymentProviderFactory::forManagedPaystack()) and its own
     * pre-configured Transaction Split, regardless of whatever gateway (if
     * any) the org has configured for its own course/membership sales.
     */
    public function checkoutRegistrationFee(User $user, MembershipApplication $application, string $callbackUrl): array
    {
        $priceCents = (int) config('services.paystack.registration_fee_cents');
        $currency = 'NGN';

        $order = DB::transaction(function () use ($user, $application, $priceCents, $currency) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'currency' => $currency,
                'subtotal_cents' => $priceCents,
                'fee_cents' => 0,
                'total_cents' => $priceCents,
                'payment_mode' => 'managed',
                'provider' => 'paystack',
            ]);

            $order->items()->create([
                'itemable_type' => $application->getMorphClass(),
                'itemable_id' => $application->id,
                'name' => 'HR GEMs membership registration fee',
                'unit_price_cents' => $priceCents,
                'quantity' => 1,
            ]);

            return $order;
        });

        $reference = "order_{$order->id}_{$order->idempotency_key}";

        $result = $this->providers->forManagedPaystack()->initializePayment([
            'email' => $user->email,
            'amount_cents' => $priceCents,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'split_code' => config('services.paystack.registration_split_code'),
        ]);

        $order->update(['provider_reference' => $result['provider_reference']]);

        return ['authorization_url' => $result['authorization_url'], 'order' => $order->fresh('items')];
    }

    /**
     * The public membership page's "pay first, then register" flow — no
     * user account exists yet, just a name + email typed into the pay
     * modal. Uses the organization's own activated PaymentConfig (whatever
     * the admin has set up at /admin/payments), unlike
     * checkoutRegistrationFee()'s platform-managed account. Matching this
     * payment to the account the payer later creates via /register (with
     * the same email) is a manual step, not reconciled automatically here.
     */
    public function checkoutMembershipFee(string $name, string $email, string $callbackUrl): array
    {
        $config = PaymentConfig::where('status', 'active')->first();

        if (! $config) {
            throw ValidationException::withMessages([
                'payment' => ['Membership payments are not configured yet. Please try again later.'],
            ]);
        }

        $priceCents = (int) config('services.membership.fee_cents');
        $currency = 'NGN';

        $payment = MembershipFeePayment::create([
            'name' => $name,
            'email' => $email,
            'amount_cents' => $priceCents,
            'currency' => $currency,
            'provider' => $config->provider,
            'status' => 'pending',
        ]);

        $reference = "membershipfee_{$payment->id}_{$payment->idempotency_key}";

        $provider = $this->providers->forConfig($config);

        $result = $provider->initializePayment([
            'email' => $email,
            'amount_cents' => $priceCents,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'subaccount_code' => $config->subaccount_code,
        ]);

        $payment->update(['provider_reference' => $result['provider_reference']]);

        return ['authorization_url' => $result['authorization_url'], 'reference' => $reference];
    }

    /**
     * Called from the public status-polling endpoint once the payer's
     * browser returns from the provider — mirrors
     * OrderFulfillmentService::reconcileRegistrationFee()'s "verify
     * directly, no webhook needed" approach, since this payment isn't tied
     * to any organization webhook subscription either.
     */
    public function reconcileMembershipFee(MembershipFeePayment $payment): MembershipFeePayment
    {
        if ($payment->isPaid() || ! $payment->provider_reference) {
            return $payment;
        }

        $config = PaymentConfig::where('status', 'active')->first();

        if (! $config) {
            return $payment;
        }

        $verified = $this->providers->forConfig($config)->verifyPayment($payment->provider_reference);

        if ($verified['status'] !== 'success') {
            return $payment;
        }

        $raw = $verified['raw'];
        unset($raw['authorization'], $raw['card'], $raw['customer']['phone']);

        $payment->update(['status' => 'paid', 'paid_at' => now(), 'raw_response' => $raw]);

        return $payment;
    }
}
