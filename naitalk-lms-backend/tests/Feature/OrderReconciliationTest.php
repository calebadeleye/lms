<?php

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentConfig;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('fulfils a stuck pending order once the provider confirms it actually succeeded', function () {
    $student = makeUserWithRole('student');

    $config = PaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'organization', 'status' => 'active']);
    $config->setSecretKey('sk_test_x');
    $config->save();
    $course = Course::create([
        'title' => 'Reconcile Course', 'slug' => 'reconcile-course', 'status' => 'published',
        'pricing_type' => 'paid', 'price_cents' => 500000, 'published_at' => now(),
    ]);
    $order = Order::create([
        'user_id' => $student->id, 'status' => 'pending', 'currency' => 'NGN',
        'subtotal_cents' => 500000, 'fee_cents' => 0, 'total_cents' => 500000,
        'payment_mode' => 'client_owned', 'provider' => 'paystack', 'provider_reference' => 'order_stuck_ref',
    ]);
    $order->items()->create([
        'itemable_type' => 'course', 'itemable_id' => $course->id, 'name' => $course->title,
        'unit_price_cents' => 500000, 'quantity' => 1,
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 500000, 'currency' => 'NGN', 'reference' => 'order_stuck_ref',
            'fees' => 7500, 'paid_at' => now()->toIso8601String(),
        ]], 200),
    ]);

    $finance = makeUserWithRole('finance-manager');
    $token = $finance->createToken('t')->plainTextToken;

    $response = $this->postJson("/api/v1/admin/orders/{$order->id}/reconcile", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data.result'))->toBe('fulfilled');
    expect($response->json('data.order.status'))->toBe('paid');

    expect(Payment::where('order_id', $order->id)->exists())->toBeTrue();
    expect(Enrolment::where('user_id', $student->id)->where('course_id', $course->id)->exists())->toBeTrue();
});

it('leaves the order pending when the provider says it was never actually paid', function () {
    $student = makeUserWithRole('student');

    $config = PaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'organization', 'status' => 'active']);
    $config->setSecretKey('sk_test_x');
    $config->save();
    $order = Order::create([
        'user_id' => $student->id, 'status' => 'pending', 'currency' => 'NGN',
        'subtotal_cents' => 500000, 'fee_cents' => 0, 'total_cents' => 500000,
        'payment_mode' => 'client_owned', 'provider' => 'paystack', 'provider_reference' => 'order_abandoned_ref',
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'abandoned', 'amount' => 500000, 'currency' => 'NGN', 'reference' => 'order_abandoned_ref',
            'fees' => 0, 'paid_at' => null,
        ]], 200),
    ]);

    $finance = makeUserWithRole('finance-manager');
    $token = $finance->createToken('t')->plainTextToken;

    $response = $this->postJson("/api/v1/admin/orders/{$order->id}/reconcile", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data.result'))->toBe('not_paid');
    expect($response->json('data.order.status'))->toBe('pending');
    expect(Payment::count())->toBe(0);
});

it('refuses reconciliation without the payments.refund permission', function () {
    $student = makeUserWithRole('student');

    PaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'status' => 'active']);
    $order = Order::create([
        'user_id' => $student->id, 'status' => 'pending', 'currency' => 'NGN',
        'subtotal_cents' => 500000, 'fee_cents' => 0, 'total_cents' => 500000,
        'payment_mode' => 'client_owned', 'provider' => 'paystack', 'provider_reference' => 'order_x',
    ]);

    $instructor = makeUserWithRole('instructor');
    $token = $instructor->createToken('t')->plainTextToken;

    $this->postJson("/api/v1/admin/orders/{$order->id}/reconcile", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(403);
});

it('exposes the webhook URL an admin must paste into their payment provider dashboard', function () {
    $config = PaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'status' => 'active']);

    $owner = makeUserWithRole('owner');
    $token = $owner->createToken('t')->plainTextToken;

    $response = $this->getJson('/api/v1/admin/payment-config', [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data.webhook_url'))->toContain("/api/v1/webhooks/paystack/{$config->webhook_token}");
});
