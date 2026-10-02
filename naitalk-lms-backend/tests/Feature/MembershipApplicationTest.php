<?php

use App\Domain\Commerce\Models\Order;
use App\Domain\Identity\Models\MembershipApplication;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('uploads');
});

function registerApplicant(array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/auth/register', array_merge([
        'name' => 'Jane Learner',
        'email' => 'jane@example.com',
        'password' => 'Passw0rd123',
        'password_confirmation' => 'Passw0rd123',
        'ack_impact_beyond_earning' => '1',
        'ack_growth_mindset' => '1',
        'ack_interest_in_coaching' => '1',
        'ack_positive_impact' => '1',
    ], $overrides));
}

it('accepts an optional welcome photo and motivation on registration', function () {
    registerApplicant([
        'motivation' => 'I want to grow as a coach.',
        'photo' => UploadedFile::fake()->image('welcome.jpg'),
    ])->assertCreated();

    $application = MembershipApplication::firstOrFail();
    expect($application->motivation)->toBe('I want to grow as a coach.');
    expect($application->photo_path)->not->toBeNull();
    Storage::disk('uploads')->assertExists($application->photo_path);
});

it('registers without a photo or motivation just fine', function () {
    registerApplicant()->assertCreated();

    $application = MembershipApplication::firstOrFail();
    expect($application->photo_path)->toBeNull();
    expect($application->motivation)->toBeNull();
});

it('reports the caller\'s own application status', function () {
    registerApplicant()->assertCreated();
    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $token = $user->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/auth/application', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('rejects a non-approver from the review queue', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/admin/applications', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

it('lets an owner list, view, and approve a pending application', function () {
    registerApplicant(['motivation' => 'Excited to join.'])->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();

    $owner = makeUserWithRole('owner');
    $ownerToken = $owner->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$ownerToken}"];

    $list = $this->getJson('/api/v1/admin/applications', $headers)->assertOk();
    expect(collect($list->json('data'))->pluck('user.email'))->toContain('jane@example.com');

    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;

    $this->getJson("/api/v1/admin/applications/{$applicationId}", $headers)
        ->assertOk()
        ->assertJsonPath('data.motivation', 'Excited to join.')
        ->assertJsonPath('data.ack_growth_mindset', true);

    // Approval requires the registration fee to be paid first.
    MembershipApplication::find($applicationId)->update(['payment_status' => 'paid']);

    $this->postJson("/api/v1/admin/applications/{$applicationId}/approve", ['note' => 'Welcome!'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($applicant->fresh()->status)->toBe('active');
    expect(MembershipApplication::find($applicationId)->reviewed_by)->toBe($owner->id);

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — without this the check below would silently pass
    // as a replay of the owner's guard resolution rather than actually
    // exercising the applicant's own token.
    $this->app->make('auth')->forgetGuards();

    // Approval is the real access gate — the applicant can now reach a
    // member-only route (email verification is a separate, earlier gate,
    // so it's satisfied directly here rather than tested twice).
    $applicant->forceFill(['email_verified_at' => now()])->save();
    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $this->getJson('/api/v1/my/enrolments', ['Authorization' => "Bearer {$applicantToken}"])->assertOk();
});

it('requires a note to reject an application, and rejecting revokes access', function () {
    registerApplicant()->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();
    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;

    $owner = makeUserWithRole('owner');
    $headers = ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken];

    $this->postJson("/api/v1/admin/applications/{$applicationId}/reject", [], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    $this->postJson("/api/v1/admin/applications/{$applicationId}/reject", ['note' => 'Not a fit right now.'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($applicant->fresh()->status)->toBe('rejected');

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — without this, the request below would keep
    // resolving the owner from the reject() call above regardless of the
    // applicant's own bearer token.
    $this->app->make('auth')->forgetGuards();

    // Verify email first so the earlier `verified` gate doesn't mask the
    // `membership_rejected` check this test is actually about.
    $applicant->forceFill(['email_verified_at' => now()])->save();
    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $response = $this->getJson('/api/v1/my/enrolments', ['Authorization' => "Bearer {$applicantToken}"])->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('membership_rejected');
});

it('serves the welcome photo only to the owning applicant or an approver', function () {
    registerApplicant(['photo' => UploadedFile::fake()->image('welcome.jpg')])->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();

    $other = makeUserWithRole('student');
    $otherToken = $other->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$otherToken}"])
        ->assertStatus(403);

    // Sanctum's RequestGuard caches the resolved user across HTTP calls
    // within one test — each actor switch below needs a fresh guard.
    $this->app->make('auth')->forgetGuards();

    $applicantToken = $applicant->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$applicantToken}"])
        ->assertOk();

    $this->app->make('auth')->forgetGuards();

    $owner = makeUserWithRole('owner');
    $ownerToken = $owner->createToken('t')->plainTextToken;
    $this->getJson("/api/v1/members/{$applicant->id}/photo", ['Authorization' => "Bearer {$ownerToken}"])
        ->assertOk();
});

// --- Registration fee (₦20,000, platform-managed Paystack + split code) --

it('refuses to approve an application that has not paid the registration fee', function () {
    registerApplicant()->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();
    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;

    $owner = makeUserWithRole('owner');
    $headers = ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken];

    $this->postJson("/api/v1/admin/applications/{$applicationId}/approve", ['note' => 'Welcome!'], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment');

    expect($applicant->fresh()->status)->toBe('pending');
});

it('does not log the applicant in at registration — no token, and the checkout must be started with the payment_token instead', function () {
    $response = registerApplicant()->assertCreated();

    expect($response->json('data'))->not->toHaveKey('token');
    $paymentToken = $response->json('data.payment_token');
    expect($paymentToken)->not->toBeEmpty();

    // No Authorization header at all — this must still work.
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('lets a not-yet-logged-in applicant start the registration-fee checkout using only the payment_token', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'order_1_ref',
        ]], 200),
    ]);

    $response = registerApplicant()->assertCreated();
    $paymentToken = $response->json('data.payment_token');

    // Deliberately no Authorization header — this is the point.
    $start = $this->postJson('/api/v1/checkout/registration-fee/start', [
        'payment_token' => $paymentToken,
        'callback_url' => 'https://app.test/checkout/callback',
    ])->assertOk();

    expect($start->json('data.authorization_url'))->toBe('https://checkout.paystack.com/abc123');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['split_code'] === config('services.paystack.registration_split_code'));
});

it('refuses to start the registration-fee checkout with a bogus payment_token', function () {
    $this->postJson('/api/v1/checkout/registration-fee/start', [
        'payment_token' => (string) \Illuminate\Support\Str::uuid(),
        'callback_url' => 'https://app.test/checkout/callback',
    ])->assertStatus(404);
});

it('marks the registration fee paid via the public reference-based status check, no session or webhook needed', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'order_1_ref',
        ]], 200),
        'api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => config('services.paystack.registration_fee_cents'),
            'currency' => 'NGN', 'reference' => 'order_1_ref', 'fees' => 0, 'paid_at' => now()->toIso8601String(),
        ]], 200),
    ]);

    $registerResponse = registerApplicant()->assertCreated();
    $paymentToken = $registerResponse->json('data.payment_token');
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();

    $this->postJson('/api/v1/checkout/registration-fee/start', [
        'payment_token' => $paymentToken,
        'callback_url' => 'https://app.test/checkout/callback',
    ])->assertOk();

    $order = Order::firstOrFail();
    $reference = "order_{$order->id}_{$order->idempotency_key}";

    // No Authorization header — this is the whole point of this endpoint.
    $this->getJson('/api/v1/checkout/registration-fee/status?reference='.urlencode($reference))
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    expect(MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->payment_status)->toBe('paid');
});

it('refuses the public status check for an order that is not a registration fee', function () {
    $student = makeUserWithRole('student');
    $order = Order::create([
        'user_id' => $student->id, 'status' => 'paid', 'currency' => 'NGN',
        'subtotal_cents' => 500000, 'fee_cents' => 0, 'total_cents' => 500000,
        'payment_mode' => 'managed', 'provider' => 'paystack', 'provider_reference' => 'order_x_ref',
    ]);
    $order->items()->create([
        'itemable_type' => 'membership_plan', 'itemable_id' => 1, 'name' => 'Some Plan',
        'unit_price_cents' => 500000, 'quantity' => 1,
    ]);

    $reference = "order_{$order->id}_{$order->idempotency_key}";

    $this->getJson('/api/v1/checkout/registration-fee/status?reference='.urlencode($reference))
        ->assertStatus(404);
});

it('lets a logged-in pending applicant retry the registration-fee checkout via the authenticated route', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'order_1_ref',
        ]], 200),
    ]);

    registerApplicant()->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();
    $token = $applicant->createToken('t')->plainTextToken;

    $response = $this->postJson('/api/v1/checkout/registration-fee', [
        'callback_url' => 'https://app.test/checkout/callback',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($response->json('data.authorization_url'))->toBe('https://checkout.paystack.com/abc123');

    $order = Order::firstOrFail();
    expect($order->total_cents)->toBe((int) config('services.paystack.registration_fee_cents'));
    expect($order->items->first()->itemable_type)->toBe('membership_application');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['split_code'] === config('services.paystack.registration_split_code')
        && $request['amount'] === (int) config('services.paystack.registration_fee_cents'));
});

it('marks the registration fee paid via the authenticated status check once retried and logged in', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'order_1_ref',
        ]], 200),
        'api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => config('services.paystack.registration_fee_cents'),
            'currency' => 'NGN', 'reference' => 'order_1_ref', 'fees' => 0, 'paid_at' => now()->toIso8601String(),
        ]], 200),
    ]);

    registerApplicant()->assertCreated();
    $applicant = User::where('email', 'jane@example.com')->firstOrFail();
    $token = $applicant->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson('/api/v1/checkout/registration-fee', ['callback_url' => 'https://app.test/checkout/callback'], $headers)
        ->assertOk();

    $order = Order::firstOrFail();

    $this->getJson("/api/v1/checkout/orders/{$order->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    expect(MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->payment_status)->toBe('paid');

    // Once paid, the payment guard no longer blocks approval.
    $this->app->make('auth')->forgetGuards();
    $owner = makeUserWithRole('owner');
    $ownerHeaders = ['Authorization' => 'Bearer '.$owner->createToken('t')->plainTextToken];
    $applicationId = MembershipApplication::where('user_id', $applicant->id)->firstOrFail()->id;
    $this->postJson("/api/v1/admin/applications/{$applicationId}/approve", ['note' => 'Welcome!'], $ownerHeaders)
        ->assertOk();
});
