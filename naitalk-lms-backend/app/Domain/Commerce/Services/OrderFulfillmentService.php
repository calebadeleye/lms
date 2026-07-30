<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Coaching\Models\Booking;
use App\Domain\Commerce\Contracts\PaymentProviderInterface;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentAllocation;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Services\EnrolmentService;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Domain\Membership\Services\MembershipService;

/**
 * The only place a paid Order turns into a real entitlement. Guarded by
 * Order::markPaid()'s status transition (see that model) — this can safely
 * be called more than once (a redelivered webhook, a race between the
 * callback page and the webhook) without granting the same thing twice,
 * because it only ever acts on an order that was still `pending`.
 */
class OrderFulfillmentService
{
    public function __construct(
        private EnrolmentService $enrolments,
        private MembershipService $memberships,
    ) {}

    /**
     * Manual fallback for an order stuck in "pending" because its webhook
     * never arrived (misconfigured URL on the provider's dashboard, lost
     * delivery, etc.) — re-checks the real status directly with the
     * provider using the order's own provider_reference and fulfils it if
     * it actually succeeded, exactly like ProcessPaymentWebhookJob would
     * have. Deliberately a separate, simpler code path rather than a
     * shared abstraction with that job — the two have different error
     * handling needs (async retry-and-log vs a synchronous admin action
     * that needs to return a plain result), and this one is short enough
     * that forcing them together isn't worth the coupling.
     */
    public function reconcileWithProvider(
        Order $order,
        PaymentConfig $config,
        PaymentProviderInterface $provider,
        CommissionService $commission,
    ): string {
        if ($order->isPaid()) {
            return 'already_paid';
        }

        if (! $order->provider_reference) {
            return 'no_reference';
        }

        $verified = $provider->verifyPayment($order->provider_reference);

        if ($verified['status'] !== 'success') {
            return 'not_paid';
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
            'org_net_cents' => $split['org_net_cents'],
            'fee_bearer' => $config->fee_bearer,
            'paid_at' => now(),
            'raw_response' => $this->stripSecrets($verified['raw']),
        ]);

        $order->markPaid();
        $this->fulfill($order, $payment);

        return 'fulfilled';
    }

    /**
     * The registration-fee equivalent of reconcileWithProvider() — no
     * PaymentConfig involved at all (see CheckoutService::checkoutRegistrationFee()),
     * so there's no org commission split to calculate: the platform receives
     * the whole amount, already divided at Paystack's end by the
     * registration split code. Called synchronously from the checkout
     * status-polling endpoint rather than waiting on a webhook, since this
     * charge isn't tied to any organization's own configured gateway/webhook.
     */
    public function reconcileRegistrationFee(Order $order, PaymentProviderInterface $provider): string
    {
        if ($order->isPaid()) {
            return 'already_paid';
        }

        if (! $order->provider_reference) {
            return 'no_reference';
        }

        $verified = $provider->verifyPayment($order->provider_reference);

        if ($verified['status'] !== 'success') {
            return 'not_paid';
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $order->provider,
            'provider_reference' => $verified['provider_reference'],
            'status' => 'success',
            'gross_amount_cents' => $verified['amount_cents'],
            'currency' => $verified['currency'],
            'provider_fee_cents' => $verified['provider_fee_cents'],
            'platform_commission_cents' => 0,
            'org_net_cents' => $verified['amount_cents'],
            'fee_bearer' => 'platform',
            'paid_at' => now(),
            'raw_response' => $this->stripSecrets($verified['raw']),
        ]);

        $order->markPaid();
        $this->fulfill($order, $payment);

        return 'fulfilled';
    }

    /** Never persist card/authorization details in raw_response — matches
     * ProcessPaymentWebhookJob's own stripSecrets(). */
    private function stripSecrets(array $raw): array
    {
        unset($raw['authorization'], $raw['card'], $raw['customer']['phone']);

        return $raw;
    }

    public function fulfill(Order $order, Payment $payment): void
    {
        foreach ($order->items as $item) {
            $allocatable = match ($item->itemable_type) {
                'course' => $this->fulfillCourse($order, $item),
                'membership_plan' => $this->fulfillMembership($order, $item),
                'booking' => $this->fulfillBooking($item),
                'membership_application' => $this->fulfillMembershipApplicationFee($item),
                default => throw new \LogicException("Unknown order item type: {$item->itemable_type}"),
            };

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'allocatable_type' => $item->itemable_type,
                'allocatable_id' => $allocatable->id,
                'amount_cents' => $item->unit_price_cents * $item->quantity,
            ]);
        }
    }

    private function fulfillCourse(Order $order, $item)
    {
        $course = Course::findOrFail($item->itemable_id);

        return $this->enrolments->enroll($order->user, $course, source: 'paid');
    }

    private function fulfillMembership(Order $order, $item)
    {
        $plan = LearnerMembershipPlan::findOrFail($item->itemable_id);

        return $this->memberships->activate($order->user, $plan);
    }

    private function fulfillBooking($item)
    {
        $booking = Booking::findOrFail($item->itemable_id);
        $booking->update(['status' => 'confirmed']);

        return $booking;
    }

    private function fulfillMembershipApplicationFee($item)
    {
        $application = MembershipApplication::findOrFail($item->itemable_id);
        $application->update(['payment_status' => 'paid']);

        return $application;
    }
}
