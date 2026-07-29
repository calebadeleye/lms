<?php

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\TenantPaymentConfig;
use App\Domain\Commerce\Models\WebhookEvent;
use App\Domain\Commerce\Services\CommissionService;
use App\Domain\Learning\Models\Course;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Commerce Co');
});

// --- Commission math (pure, no HTTP) ------------------------------------

it('calculates the platform default 1% commission for managed-mode payments', function () {
    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'managed', 'fee_bearer' => 'tenant']);
    app(TenantContext::class)->clear();

    $split = app(CommissionService::class)->calculate($config, grossAmountCents: 10_000_00, providerFeeCents: 150_00);

    expect($split['commission_cents'])->toBe(10_000); // 1% of 1,000,000
    expect($split['tenant_net_cents'])->toBe(10_000_00 - 10_000 - 150_00);
});

it('respects a per-tenant commission percent override', function () {
    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create([
        'provider' => 'paystack', 'mode' => 'managed', 'fee_bearer' => 'tenant', 'commission_percent' => 2.5,
    ]);
    app(TenantContext::class)->clear();

    $split = app(CommissionService::class)->calculate($config, grossAmountCents: 10_000_00);

    expect($split['commission_cents'])->toBe(25_000); // 2.5% of 1,000,000
});

it('charges zero commission for client-owned mode — NAI TALK never touches that money', function () {
    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'tenant']);
    app(TenantContext::class)->clear();

    $split = app(CommissionService::class)->calculate($config, grossAmountCents: 10_000_00, providerFeeCents: 150_00);

    expect($split['commission_cents'])->toBe(0);
    expect($split['tenant_net_cents'])->toBe(10_000_00 - 150_00);
});

// --- Gateway modes -------------------------------------------------------

it('lets a tenant connect their own client-owned Paystack credentials', function () {
    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    $response = $this->putJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/client-owned'), [
        'provider' => 'paystack',
        'public_key' => 'pk_test_abc123',
        'secret_key' => 'sk_test_supersecret',
        'webhook_secret' => 'whsec_abc',
        'fee_bearer' => 'tenant',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($response->json('data.mode'))->toBe('client_owned');
    expect($response->json('data.has_secret_configured'))->toBeTrue();
    // The raw secret must never come back in any API response.
    expect($response->getContent())->not->toContain('sk_test_supersecret');

    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::first();
    app(TenantContext::class)->clear();
    expect($config->getSecretKey())->toBe('sk_test_supersecret'); // but is retrievable server-side when decrypted
});

it('lets a tenant connect NAI TALK-managed payments and creates a provider subaccount', function () {
    Http::fake([
        'api.paystack.co/subaccount' => Http::response(['data' => ['subaccount_code' => 'ACCT_test123']], 200),
    ]);

    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    $response = $this->putJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/managed'), [
        'provider' => 'paystack',
        'business_name' => 'Commerce Co',
        'settlement_bank_code' => '058',
        'account_number' => '0123456789',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($response->json('data.mode'))->toBe('managed');
    expect($response->json('data.subaccount_code'))->toBe('ACCT_test123');
    // PHP's json_encode collapses a whole-number float to a JSON integer,
    // so json_decode gives back int 1 here — toEqual does a loose (==)
    // comparison rather than toBe's strict type-and-value check.
    expect($response->json('data.commission_percent'))->toEqual(1.0); // platform default

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/subaccount'
        && $request['percentage_charge'] === 1.0);
});

it('cannot read another tenant\'s payment config', function () {
    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Commerce Co');
    app(TenantContext::class)->set($otherTenant);
    TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'public_key' => 'pk_other']);
    app(TenantContext::class)->clear();

    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    $response = $this->getJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config'), [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data'))->toBeNull();
});

// --- Test connection: must reflect the provider's real response, not just
// "wasn't a 401" — a 400/403/500 (or a network failure) is not "connected".

it('reports a connected gateway when the provider returns a successful response', function () {
    Http::fake(['api.paystack.co/balance' => Http::response(['data' => []], 200)]);

    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    $this->putJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/client-owned'), [
        'provider' => 'paystack', 'public_key' => 'pk_test_abc', 'secret_key' => 'sk_test_abc',
        'webhook_secret' => 'whsec_abc', 'fee_bearer' => 'tenant',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->postJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/test-connection'), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('data.connected', true);
});

it('reports a disconnected gateway for invalid credentials, even on a non-401 error status', function () {
    // Regression: the old check was `status() !== 401`, which would have
    // treated this 403 (or any other non-401 failure) as "connected".
    Http::fake(['api.paystack.co/balance' => Http::response(['message' => 'Forbidden'], 403)]);

    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    $this->putJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/client-owned'), [
        'provider' => 'paystack', 'public_key' => 'pk_test_bad', 'secret_key' => 'sk_test_bad',
        'webhook_secret' => 'whsec_bad', 'fee_bearer' => 'tenant',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->postJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/test-connection'), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('data.connected', false);
});

it('reports a disconnected gateway rather than erroring when the provider is unreachable', function () {
    Http::fake(['api.paystack.co/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('could not connect')]);

    $owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $token = $owner->createToken('t')->plainTextToken;

    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'tenant']);
    $config->setSecretKey('sk_test_x');
    $config->setWebhookSecret('whsec_x');
    $config->save();
    app(TenantContext::class)->clear();

    $this->postJson(tenantUrl($this->tenant, '/api/v1/admin/payment-config/test-connection'), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('data.connected', false);
});

// --- Webhooks: verified + idempotent + queued fulfillment ---------------

it('rejects a webhook with an invalid signature', function () {
    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'tenant']);
    $config->setSecretKey('sk_test_x');
    $config->setWebhookSecret('whsec_correct');
    $config->save();
    app(TenantContext::class)->clear();

    $this->postJson("/api/v1/webhooks/paystack/{$config->webhook_token}", [
        'event' => 'charge.success',
        'data' => ['reference' => 'order_1_abc', 'id' => 999],
    ], ['X-Paystack-Signature' => 'not-the-right-signature'])
        ->assertStatus(401);
});

it('verifies a webhook, fulfills the order exactly once, and computes the 1% commission', function () {
    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'managed', 'fee_bearer' => 'tenant']);

    $course = Course::create([
        'title' => 'Paid Course', 'slug' => 'paid-course', 'status' => 'published',
        'pricing_type' => 'paid', 'price_cents' => 500000, 'published_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    $student = makeUserWithRole($this->tenant, 'student');

    app(TenantContext::class)->set($this->tenant);
    $order = Order::create([
        'user_id' => $student->id, 'status' => 'pending', 'currency' => 'NGN',
        'subtotal_cents' => 500000, 'fee_cents' => 0, 'total_cents' => 500000,
        'payment_mode' => 'managed', 'provider' => 'paystack', 'provider_reference' => 'order_1_ref',
    ]);
    $order->items()->create([
        'itemable_type' => 'course', 'itemable_id' => $course->id, 'name' => $course->title,
        'unit_price_cents' => 500000, 'quantity' => 1,
    ]);
    app(TenantContext::class)->clear();

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 500000, 'currency' => 'NGN', 'reference' => 'order_1_ref',
            'fees' => 7500, 'paid_at' => now()->toIso8601String(),
        ]], 200),
    ]);

    $payload = ['event' => 'charge.success', 'data' => ['reference' => 'order_1_ref', 'id' => 555]];
    $body = json_encode($payload);
    // Managed mode: Paystack signs with the same secret key used for API
    // calls (there's no separate webhook secret), and that's NAI TALK's own
    // platform key here — see phpunit.xml's PAYSTACK_SECRET_KEY.
    $signature = hash_hmac('sha512', $body, config('services.paystack.secret_key'));

    // First delivery: processes and fulfills.
    $this->call('POST', "/api/v1/webhooks/paystack/{$config->webhook_token}", [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    $order->refresh();
    expect($order->status)->toBe('paid');

    $payment = Payment::first();
    expect($payment->gross_amount_cents)->toBe(500000);
    expect($payment->platform_commission_cents)->toBe(5000); // 1% of 500,000
    expect($payment->tenant_net_cents)->toBe(500000 - 5000 - 7500);

    $enrolled = \App\Domain\Learning\Models\Enrolment::where('user_id', $student->id)->where('course_id', $course->id)->exists();
    expect($enrolled)->toBeTrue();
    app(TenantContext::class)->clear();

    // Second delivery of the SAME event: must not create a second payment
    // or re-fulfill (idempotency_key / webhook_events unique index).
    $this->call('POST', "/api/v1/webhooks/paystack/{$config->webhook_token}", [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(Payment::count())->toBe(1);
    expect(WebhookEvent::count())->toBe(1);
});

it('lets a finance manager issue a refund', function () {
    Http::fake([
        'api.paystack.co/refund' => Http::response(['data' => [
            'status' => 'processed', 'amount' => 200000, 'transaction' => ['reference' => 'order_2_ref'],
        ]], 200),
    ]);

    $student = makeUserWithRole($this->tenant, 'student');

    app(TenantContext::class)->set($this->tenant);
    $config = TenantPaymentConfig::create(['provider' => 'paystack', 'mode' => 'client_owned', 'fee_bearer' => 'tenant']);
    $config->setSecretKey('sk_test_x');
    $config->save();

    $order = Order::create([
        'user_id' => $student->id, 'status' => 'paid', 'currency' => 'NGN',
        'subtotal_cents' => 200000, 'fee_cents' => 0, 'total_cents' => 200000,
        'payment_mode' => 'client_owned', 'provider' => 'paystack', 'provider_reference' => 'order_2_ref', 'paid_at' => now(),
    ]);
    $payment = Payment::create([
        'order_id' => $order->id, 'provider' => 'paystack', 'provider_reference' => 'order_2_ref', 'status' => 'success',
        'gross_amount_cents' => 200000, 'currency' => 'NGN', 'fee_bearer' => 'tenant', 'paid_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    $finance = makeUserWithRole($this->tenant, 'finance-manager');
    $token = $finance->createToken('t')->plainTextToken;

    $response = $this->postJson(tenantUrl($this->tenant, "/api/v1/admin/payments/{$payment->id}/refund"), [
        'reason' => 'Learner requested a refund',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    expect($response->json('data.status'))->toBe('processed');

    app(TenantContext::class)->set($this->tenant);
    expect($order->fresh()->status)->toBe('refunded');
    app(TenantContext::class)->clear();
});
