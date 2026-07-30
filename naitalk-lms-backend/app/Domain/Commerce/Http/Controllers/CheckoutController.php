<?php

namespace App\Domain\Commerce\Http\Controllers;

use App\Domain\Coaching\Models\Booking;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\OrderFulfillmentService;
use App\Domain\Commerce\Services\PaymentProviderFactory;
use App\Domain\Identity\Models\MembershipApplication;
use App\Domain\Learning\Models\Course;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private OrderFulfillmentService $fulfillment,
        private PaymentProviderFactory $providers,
    ) {}

    /** The applicant pays this before their registration is reviewable —
     * see MembershipApplicationService::approve()'s payment_status guard. */
    public function registrationFee(Request $request)
    {
        $application = MembershipApplication::where('user_id', $request->user()->id)->firstOrFail();

        if ($application->isPaid()) {
            throw ValidationException::withMessages(['payment' => ['The registration fee has already been paid.']]);
        }

        $data = $request->validate(['callback_url' => ['required', 'url']]);

        $result = $this->checkout->checkoutRegistrationFee($request->user(), $application, $data['callback_url']);

        return response()->json(['data' => $result]);
    }

    public function course(Request $request, string $courseId)
    {
        $course = Course::where('status', 'published')->where('pricing_type', 'paid')->findOrFail($courseId);
        $data = $request->validate(['callback_url' => ['required', 'url']]);

        $result = $this->checkout->checkout(
            $request->user(), $course, $course->title, $course->price_cents, $course->currency, $data['callback_url']
        );

        return response()->json(['data' => $result]);
    }

    public function membership(Request $request, string $planId)
    {
        $plan = LearnerMembershipPlan::where('is_active', true)->findOrFail($planId);
        $data = $request->validate(['callback_url' => ['required', 'url']]);

        $result = $this->checkout->checkout(
            $request->user(), $plan, $plan->name, $plan->price_cents, $plan->currency, $data['callback_url']
        );

        return response()->json(['data' => $result]);
    }

    /** The Booking must already exist with status=pending (see BookingController::store). */
    public function coachingBooking(Request $request, string $bookingId)
    {
        $booking = Booking::where('user_id', $request->user()->id)->where('status', 'pending')->with('session.service')->findOrFail($bookingId);
        $data = $request->validate(['callback_url' => ['required', 'url']]);

        $service = $booking->session->service;

        if ($service->is_free) {
            throw ValidationException::withMessages(['booking' => ['This session is free — no checkout needed.']]);
        }

        $result = $this->checkout->checkout(
            $request->user(), $booking, $service->title, $service->price_cents, $service->currency, $data['callback_url']
        );

        return response()->json(['data' => $result]);
    }

    /**
     * The frontend calls this right after the provider redirects back, to
     * show an immediate result — the webhook is still the authoritative
     * source that actually fulfills the order, this just reflects whatever
     * state has been reached so far (which may still be "pending" if the
     * webhook hasn't landed yet).
     */
    public function status(Request $request, string $orderId)
    {
        $order = Order::where('user_id', $request->user()->id)->with('items', 'payment')->findOrFail($orderId);

        // Registration-fee orders have no organization gateway/webhook to
        // wait on (see checkoutRegistrationFee()) — reconcile with the
        // provider directly, right here, the moment the applicant's browser
        // comes back and starts polling. Safe to call on every poll:
        // reconcileRegistrationFee() is a no-op once already paid.
        if ($order->status === 'pending' && $order->items->first()?->itemable_type === 'membership_application') {
            $this->fulfillment->reconcileRegistrationFee($order, $this->providers->forManagedPaystack());
            $order = $order->fresh(['items', 'payment']);
        }

        return response()->json(['data' => [
            'status' => $order->status,
            'total_cents' => $order->total_cents,
            'currency' => $order->currency,
            'items' => $order->items,
        ]]);
    }
}
