<?php

use App\Domain\Identity\Models\MembershipApplication;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('registers a new applicant with a pending status and a membership application', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Learner',
        'email' => 'jane@example.com',
        'password' => 'Passw0rd123',
        'password_confirmation' => 'Passw0rd123',
        'ack_impact_beyond_earning' => '1',
        'ack_growth_mindset' => '1',
        'ack_interest_in_coaching' => '1',
        'ack_positive_impact' => '1',
    ])->assertCreated();

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.membership_status'))->toBe('pending');

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    expect($user->status)->toBe('pending');
    expect($user->role->slug)->toBe('student');
    // Self-registration must never auto-verify — that would defeat the
    // point of sending a verification email at all.
    expect($user->email_verified_at)->toBeNull();

    $application = MembershipApplication::where('user_id', $user->id)->firstOrFail();
    expect($application->status)->toBe('pending');
    expect($application->ack_impact_beyond_earning)->toBeTrue();
});

it('rejects registration missing an acknowledgement', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Learner',
        'email' => 'jane@example.com',
        'password' => 'Passw0rd123',
        'password_confirmation' => 'Passw0rd123',
        'ack_impact_beyond_earning' => '1',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['ack_growth_mindset', 'ack_interest_in_coaching', 'ack_positive_impact']);
});

it('blocks an unverified student from member actions but not from checking their own status', function () {
    $student = User::factory()->unverified()->create(['role_id' => \App\Domain\Identity\Models\Role::where('slug', 'student')->firstOrFail()->id, 'status' => 'active']);

    $token = $student->createToken('test')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $response = $this->getJson('/api/v1/my/enrolments', $headers)->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('email_not_verified');

    // Still reachable while unverified — checking status, resending, and
    // logging out must never be blocked by the same gate they're meant to
    // get you out of.
    $this->getJson('/api/v1/auth/me', $headers)
        ->assertOk()
        ->assertJsonPath('data.user.email_verified_at', null);
    $this->postJson('/api/v1/auth/email/resend', [], $headers)->assertOk();
});

it('blocks a pending (unapproved) student from member actions but not from checking their own application', function () {
    $student = makeUserWithRole('student', status: 'pending');
    $token = $student->createToken('test')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $response = $this->getJson('/api/v1/my/enrolments', $headers)->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('membership_pending');

    $this->getJson('/api/v1/auth/me', $headers)
        ->assertOk()
        ->assertJsonPath('data.membership_status', 'pending');
});

it('blocks a rejected student with a distinct error code', function () {
    $student = makeUserWithRole('student', status: 'rejected');
    $token = $student->createToken('test')->plainTextToken;

    $response = $this->getJson('/api/v1/my/enrolments', ['Authorization' => "Bearer {$token}"])->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('membership_rejected');
});

it('lets an approved (active) student use member actions normally', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('test')->plainTextToken;

    $this->getJson('/api/v1/my/enrolments', [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();
});

it('lets an administrator list members, but not a student', function () {
    $admin = makeUserWithRole('administrator', status: 'active');
    $admin->update(['name' => 'Ada Admin']);
    $student = makeUserWithRole('student');

    $adminToken = $admin->createToken('t')->plainTextToken;

    $response = $this->getJson('/api/v1/admin/members', [
        'Authorization' => "Bearer {$adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('name'))->toContain('Ada Admin');

    // Laravel's AuthManager caches resolved guards for the container's
    // lifetime, which spans every in-process HTTP call in one test method —
    // without this, the sanctum guard keeps resolving the first user
    // (admin) instead of re-checking the student's token below.
    $this->app->make('auth')->forgetGuards();

    $studentToken = $student->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/admin/members', [
        'Authorization' => "Bearer {$studentToken}",
    ])->assertStatus(403);
});

it('rejects login with wrong credentials', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(422);
});

it('locks out login after repeated failures', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com', 'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(422);

    expect($response->json('errors.email.0'))->toContain('Too many login attempts');
});

it('allows a pending or rejected user to still log in (only inactive is rejected outright)', function () {
    $pending = makeUserWithRole('student', status: 'pending');

    $this->postJson('/api/v1/auth/login', [
        'email' => $pending->email, 'password' => 'Passw0rd123',
    ])->assertOk();
});

it('rejects login for a deactivated (inactive) account', function () {
    $inactive = makeUserWithRole('student', status: 'inactive');

    $this->postJson('/api/v1/auth/login', [
        'email' => $inactive->email, 'password' => 'Passw0rd123',
    ])->assertStatus(422);
});

it('enforces permission middleware on admin endpoints', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('test')->plainTextToken;

    // Students have zero permissions — settings.manage must be denied.
    $this->postJson('/api/v1/admin/testimonials', ['quote' => 'Great course', 'author' => 'A. Learner'], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(403);
});

it('allows an owner to manage testimonials', function () {
    $owner = makeUserWithRole('owner');
    $token = $owner->createToken('test')->plainTextToken;

    $this->postJson('/api/v1/admin/testimonials', ['quote' => 'Great course', 'author' => 'A. Learner'], [
        'Authorization' => "Bearer {$token}",
    ])->assertCreated()->assertJsonPath('data.author', 'A. Learner');
});

it('revokes a session so its token no longer authenticates', function () {
    $owner = makeUserWithRole('owner');

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $owner->email, 'password' => 'Passw0rd123',
    ])->assertOk();

    $token = $login->json('data.token');

    $this->getJson('/api/v1/auth/me', [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    // Laravel's AuthManager caches resolved guards for the lifetime of the
    // container, which in a single test method spans multiple in-process
    // HTTP calls — without this, the sanctum guard would keep returning the
    // user it resolved on the previous call instead of re-checking the
    // (now-deleted) token.
    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/v1/auth/me', [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(401);
});
