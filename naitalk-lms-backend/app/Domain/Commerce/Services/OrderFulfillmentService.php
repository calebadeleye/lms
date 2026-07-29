<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Coaching\Models\Booking;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentAllocation;
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

    public function fulfill(Order $order, Payment $payment): void
    {
        foreach ($order->items as $item) {
            $allocatable = match ($item->itemable_type) {
                'course' => $this->fulfillCourse($order, $item),
                'membership_plan' => $this->fulfillMembership($order, $item),
                'booking' => $this->fulfillBooking($item),
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
}
