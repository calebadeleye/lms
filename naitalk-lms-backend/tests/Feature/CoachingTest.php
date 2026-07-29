<?php

use App\Domain\Coaching\Models\AvailabilityRule;
use App\Domain\Coaching\Models\Booking;
use App\Domain\Coaching\Models\Coach;
use App\Domain\Coaching\Models\CoachingService;
use App\Domain\Coaching\Models\CoachingSession;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Coaching Co');

    // A fixed future Monday 09:00-17:00 UTC availability window, so tests
    // never depend on "now" landing inside/outside business hours.
    $this->withinHours = Carbon::parse('next monday 10:00', 'UTC')->addWeek();
    $this->outsideHours = $this->withinHours->clone()->setTime(20, 0);

    app(TenantContext::class)->set($this->tenant);
    $coachUser = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    $this->coach = Coach::create([
        'user_id' => $coachUser->id, 'title' => 'Career Coach', 'timezone' => 'UTC', 'is_active' => true,
    ]);
    AvailabilityRule::create([
        'coach_id' => $this->coach->id, 'day_of_week' => $this->withinHours->dayOfWeek,
        'start_time' => '09:00:00', 'end_time' => '17:00:00', 'timezone' => 'UTC',
    ]);
    $this->oneToOneService = CoachingService::create([
        'coach_id' => $this->coach->id, 'title' => '1:1 Session', 'session_type' => 'one_to_one',
        'duration_minutes' => 30, 'is_free' => true, 'max_participants' => 1, 'is_active' => true,
    ]);
    $this->groupService = CoachingService::create([
        'coach_id' => $this->coach->id, 'title' => 'Group Session', 'session_type' => 'group',
        'duration_minutes' => 60, 'is_free' => true, 'max_participants' => 2, 'is_active' => true,
    ]);
    app(TenantContext::class)->clear();
});

it('lets a student book a one-to-one session within the coach\'s availability', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;

    $response = $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-services/{$this->oneToOneService->id}/book"), [
        'scheduled_start' => $this->withinHours->toIso8601String(),
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    expect($response->json('data.status'))->toBe('confirmed'); // free service auto-confirms
});

it('rejects a one-to-one booking outside the coach\'s available hours', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;

    $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-services/{$this->oneToOneService->id}/book"), [
        'scheduled_start' => $this->outsideHours->toIso8601String(),
    ], ['Authorization' => "Bearer {$token}"])->assertStatus(422);
});

it('rejects a second one-to-one booking that overlaps the coach\'s existing session', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-services/{$this->oneToOneService->id}/book"), [
        'scheduled_start' => $this->withinHours->toIso8601String(),
    ], $headers)->assertCreated();

    // Same coach (only one coaching service exists for this coach in this
    // window), overlapping time — must conflict even though it's a
    // different booking attempt by the same user.
    $overlapping = $this->withinHours->clone()->addMinutes(15);
    $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-services/{$this->oneToOneService->id}/book"), [
        'scheduled_start' => $overlapping->toIso8601String(),
    ], $headers)->assertStatus(422);
});

it('rejects booking into a group session once max_participants is reached', function () {
    app(TenantContext::class)->set($this->tenant);
    $session = CoachingSession::create([
        'coaching_service_id' => $this->groupService->id, 'coach_id' => $this->coach->id,
        'scheduled_start' => $this->withinHours, 'scheduled_end' => $this->withinHours->clone()->addHour(),
        'status' => 'scheduled',
    ]);
    // Fill both seats directly — the point of this test is the rejection
    // path, not re-proving a fresh booking succeeds (covered above).
    Booking::create(['coaching_session_id' => $session->id, 'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'booked_at' => now()]);
    Booking::create(['coaching_session_id' => $session->id, 'user_id' => User::factory()->create()->id, 'status' => 'confirmed', 'booked_at' => now()]);
    app(TenantContext::class)->clear();

    $lateStudent = makeUserWithRole($this->tenant, 'student');
    $token = $lateStudent->createToken('t')->plainTextToken;

    $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-sessions/{$session->id}/book"), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(422);
});

it('frees the coach\'s calendar when a one-to-one booking is cancelled', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $book = $this->postJson(tenantUrl($this->tenant, "/api/v1/coaching-services/{$this->oneToOneService->id}/book"), [
        'scheduled_start' => $this->withinHours->toIso8601String(),
    ], $headers)->assertCreated();

    $bookingId = $book->json('data.id');
    $sessionId = $book->json('data.session.id') ?? $book->json('data.coaching_session_id');

    $this->postJson(tenantUrl($this->tenant, "/api/v1/bookings/{$bookingId}/cancel"), [], $headers)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(CoachingSession::find($sessionId)->status)->toBe('cancelled');
    app(TenantContext::class)->clear();
});

it('scopes coaches to their own tenant on the public catalogue', function () {
    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Coaching Co');
    app(TenantContext::class)->set($otherTenant);
    Coach::create(['user_id' => User::factory()->create()->id, 'title' => 'Other Coach', 'timezone' => 'UTC', 'is_active' => true]);
    app(TenantContext::class)->clear();

    $response = $this->getJson(tenantUrl($this->tenant, '/api/v1/coaches'))->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Career Coach');
    expect($titles)->not->toContain('Other Coach');
});
