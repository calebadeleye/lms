<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Acme Academy');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    // Symfony's Request::create() re-derives HTTP_HOST from the URI itself
    // when the URI has a host component, clobbering any separately-passed
    // Host header — so tenant-domain tests must hit a full tenant URL
    // rather than a relative path + ['Host' => ...] header.
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";
});

it('registers a new student and assigns the tenant\'s student role', function () {
    $response = $this->postJson(($this->url)('/api/v1/auth/register'), [
        'name' => 'Jane Learner',
        'email' => 'jane@example.com',
        'password' => 'Passw0rd123',
        'password_confirmation' => 'Passw0rd123',
    ])->assertCreated();

    expect($response->json('data.token'))->not->toBeEmpty();

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $membership = TenantUser::where('user_id', $user->id)->where('tenant_id', $this->tenant->id)->first();

    expect($membership)->not->toBeNull();
    expect($membership->role->slug)->toBe('student');
    // Self-registration must never auto-verify — that would defeat the
    // point of sending a verification email at all.
    expect($user->email_verified_at)->toBeNull();
});

it('blocks an unverified student from tenant-scoped actions but not from checking their own status', function () {
    app(TenantContext::class)->set($this->tenant);
    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $student = User::factory()->unverified()->create();
    TenantUser::create(['user_id' => $student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $student->createToken('test')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $response = $this->getJson(($this->url)('/api/v1/my/enrolments'), $headers)->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('email_not_verified');

    // Still reachable while unverified — checking status, resending, and
    // logging out must never be blocked by the same gate they're meant to
    // get you out of.
    $this->getJson(($this->url)('/api/v1/auth/me'), $headers)
        ->assertOk()
        ->assertJsonPath('data.user.email_verified_at', null);
    $this->postJson(($this->url)('/api/v1/auth/email/resend'), [], $headers)->assertOk();
});

it('lets a verified student use tenant-scoped actions normally', function () {
    app(TenantContext::class)->set($this->tenant);
    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $student = User::factory()->create(); // factory default is verified
    TenantUser::create(['user_id' => $student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $student->createToken('test')->plainTextToken;

    $this->getJson(($this->url)('/api/v1/my/enrolments'), [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();
});

it('lets a tenant-administrator list active tenant members, but not a student', function () {
    app(TenantContext::class)->set($this->tenant);
    $adminRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-administrator')->firstOrFail();
    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $admin = User::factory()->create(['name' => 'Ada Admin']);
    $student = User::factory()->create();
    TenantUser::create(['user_id' => $admin->id, 'role_id' => $adminRole->id, 'status' => 'active', 'joined_at' => now()]);
    TenantUser::create(['user_id' => $student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $adminToken = $admin->createToken('t')->plainTextToken;

    $response = $this->getJson(($this->url)('/api/v1/admin/tenant-users'), [
        'Authorization' => "Bearer {$adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('name'))->toContain('Ada Admin');

    // Laravel's AuthManager caches resolved guards for the container's
    // lifetime, which spans every in-process HTTP call in one test method —
    // without this, the sanctum guard keeps resolving the first user
    // (admin) instead of re-checking the student's token below.
    $this->app->make('auth')->forgetGuards();

    $studentToken = $student->createToken('t')->plainTextToken;

    $this->getJson(($this->url)('/api/v1/admin/tenant-users'), [
        'Authorization' => "Bearer {$studentToken}",
    ])->assertStatus(403);
});

it('rejects login with wrong credentials', function () {
    $this->postJson(($this->url)('/api/v1/auth/login'), [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(422);
});

it('locks out login after repeated failures', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(($this->url)('/api/v1/auth/login'), [
            'email' => 'nobody@example.com', 'password' => 'wrong',
        ]);
    }

    $response = $this->postJson(($this->url)('/api/v1/auth/login'), [
        'email' => 'nobody@example.com', 'password' => 'wrong',
    ])->assertStatus(422);

    expect($response->json('errors.email.0'))->toContain('Too many login attempts');
});

it('enforces permission middleware on tenant-scoped endpoints', function () {
    app(TenantContext::class)->set($this->tenant);
    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $student = User::factory()->create();
    TenantUser::create(['user_id' => $student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $student->createToken('test')->plainTextToken;

    // Students have zero permissions — branding.manage must be denied.
    $this->putJson(($this->url)('/api/v1/tenant/branding'), ['primary_color' => '#000000'], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(403);
});

it('allows a tenant owner to update branding', function () {
    app(TenantContext::class)->set($this->tenant);
    $ownerRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-owner')->firstOrFail();
    $owner = User::factory()->create();
    TenantUser::create(['user_id' => $owner->id, 'role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $owner->createToken('test')->plainTextToken;

    $this->putJson(($this->url)('/api/v1/tenant/branding'), ['primary_color' => '#123456'], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('data.primary_color', '#123456');
});

it('revokes a session so its token no longer authenticates', function () {
    app(TenantContext::class)->set($this->tenant);
    $ownerRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-owner')->firstOrFail();
    $owner = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    TenantUser::create(['user_id' => $owner->id, 'role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $login = $this->postJson(($this->url)('/api/v1/auth/login'), [
        'email' => $owner->email, 'password' => 'Passw0rd123',
    ])->assertOk();

    $token = $login->json('data.token');

    $this->getJson(($this->url)('/api/v1/auth/me'), [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    $this->postJson(($this->url)('/api/v1/auth/logout'), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    // Laravel's AuthManager caches resolved guards for the lifetime of the
    // container, which in a single test method spans multiple in-process
    // HTTP calls — without this, the sanctum guard would keep returning the
    // user it resolved on the previous call instead of re-checking the
    // (now-deleted) token.
    $this->app->make('auth')->forgetGuards();

    $this->getJson(($this->url)('/api/v1/auth/me'), [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(401);
});
