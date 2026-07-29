<?php

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Identity\Notifications\AddedToTenantNotification;
use App\Domain\Identity\Notifications\TenantInvitationNotification;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Team Co');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    $this->admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
});

// --- Roles + member listing --------------------------------------------

it('lists assignable roles for the tenant', function () {
    $response = $this->getJson(($this->url)('/api/v1/admin/roles'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->toContain('student', 'instructor');
});

it('only lists active members by default, and everyone with include_inactive', function () {
    $student = makeUserWithRole($this->tenant, 'student');

    app(TenantContext::class)->set($this->tenant);
    TenantUser::where('user_id', $student->id)->update(['status' => 'inactive']);
    app(TenantContext::class)->clear();

    $default = $this->getJson(($this->url)('/api/v1/admin/tenant-users'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();
    expect(collect($default->json('data'))->pluck('id'))->not->toContain($student->id);

    $all = $this->getJson(($this->url)('/api/v1/admin/tenant-users?include_inactive=1'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();
    expect(collect($all->json('data'))->pluck('id'))->toContain($student->id);
});

it('returns the full unpaginated array when no page param is given, for pickers', function () {
    makeUserWithRole($this->tenant, 'student');
    makeUserWithRole($this->tenant, 'instructor');

    $response = $this->getJson(($this->url)('/api/v1/admin/tenant-users'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect($response->json('meta'))->toBeNull();
    expect(is_array($response->json('data')))->toBeTrue();
});

it('paginates and reports meta when a page param is given', function () {
    for ($i = 0; $i < 3; $i++) {
        makeUserWithRole($this->tenant, 'student');
    }

    $response = $this->getJson(($this->url)('/api/v1/admin/tenant-users?page=1&per_page=2'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('meta.pagination.per_page'))->toBe(2);
    expect($response->json('meta.pagination.total'))->toBeGreaterThanOrEqual(4);
});

it('searches members by name or email', function () {
    $match = makeUserWithRole($this->tenant, 'student');
    $match->update(['name' => 'Zanele Distinct', 'email' => 'zanele@example.com']);

    $response = $this->getJson(($this->url)('/api/v1/admin/tenant-users?search=Zanele'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($match->id);
    expect(collect($response->json('data')))->toHaveCount(1);
});

// --- Inviting ------------------------------------------------------------

it('invites a brand-new email and sends an invitation notification', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $instructorRole = Role::forTenant($this->tenant->id)->where('slug', 'instructor')->firstOrFail();
    app(TenantContext::class)->clear();

    $response = $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'newperson@example.com', 'role_id' => $instructorRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    expect($response->json('data.email'))->toBe('newperson@example.com');

    Notification::assertSentOnDemand(TenantInvitationNotification::class);

    app(TenantContext::class)->set($this->tenant);
    expect(Invitation::where('email', 'newperson@example.com')->where('status', 'pending')->exists())->toBeTrue();
    app(TenantContext::class)->clear();
});

it('adds an existing user directly instead of creating an invitation', function () {
    Notification::fake();

    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Co');
    $existingUser = User::factory()->create(['email' => 'already@example.com']);
    app(TenantContext::class)->set($otherTenant);
    TenantUser::create([
        'user_id' => $existingUser->id,
        'role_id' => Role::forTenant($otherTenant->id)->where('slug', 'student')->firstOrFail()->id,
        'status' => 'active', 'joined_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    app(TenantContext::class)->set($this->tenant);
    $coachRole = Role::forTenant($this->tenant->id)->where('slug', 'coach')->firstOrFail();
    app(TenantContext::class)->clear();

    $response = $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'already@example.com', 'role_id' => $coachRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    expect($response->json('data.status'))->toBe('added_existing_user');
    Notification::assertSentOnDemand(AddedToTenantNotification::class);

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $existingUser->id)->where('status', 'active')->exists())->toBeTrue();
    expect(Invitation::count())->toBe(0);
    app(TenantContext::class)->clear();
});

it('refuses a second invitation while one is already pending for the same email', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    app(TenantContext::class)->clear();

    $payload = ['email' => 'dup@example.com', 'role_id' => $role->id];
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->postJson(($this->url)('/api/v1/admin/invitations'), $payload, $headers)->assertCreated();
    $this->postJson(($this->url)('/api/v1/admin/invitations'), $payload, $headers)->assertStatus(422);
});

it('lets an admin revoke a pending invitation', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    app(TenantContext::class)->clear();

    $headers = ['Authorization' => "Bearer {$this->adminToken}"];
    $created = $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'revoke-me@example.com', 'role_id' => $role->id,
    ], $headers)->assertCreated();

    $this->deleteJson(($this->url)("/api/v1/admin/invitations/{$created->json('data.id')}"), [], $headers)->assertOk();

    $pending = $this->getJson(($this->url)('/api/v1/admin/invitations'), $headers)->assertOk();
    expect(collect($pending->json('data'))->pluck('email'))->not->toContain('revoke-me@example.com');
});

// --- Accepting -------------------------------------------------------------

it('previews a pending invitation publicly', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'instructor')->firstOrFail();
    app(TenantContext::class)->clear();

    $created = $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'previewer@example.com', 'role_id' => $role->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    app(TenantContext::class)->set($this->tenant);
    $token = Invitation::where('email', 'previewer@example.com')->firstOrFail()->token;
    app(TenantContext::class)->clear();

    $preview = $this->getJson(($this->url)("/api/v1/invitations/{$token}"))->assertOk();
    expect($preview->json('data.tenant_name'))->toBe('Team Co');
    expect($preview->json('data.role_name'))->toBe('Instructor');
});

it('accepts an invitation, creating a verified, immediately-usable account', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'instructor')->firstOrFail();
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'accepter@example.com', 'role_id' => $role->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    app(TenantContext::class)->set($this->tenant);
    $token = Invitation::where('email', 'accepter@example.com')->firstOrFail()->token;
    app(TenantContext::class)->clear();

    $response = $this->postJson(($this->url)("/api/v1/invitations/{$token}/accept"), [
        'name' => 'Accepter Person', 'password' => 'Passw0rd123', 'password_confirmation' => 'Passw0rd123',
    ])->assertCreated();

    expect($response->json('data.token'))->not->toBeNull();

    $user = User::where('email', 'accepter@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();

    app(TenantContext::class)->set($this->tenant);
    $member = TenantUser::where('user_id', $user->id)->firstOrFail();
    expect($member->role->slug)->toBe('instructor');
    expect($member->status)->toBe('active');
    expect(Invitation::where('email', 'accepter@example.com')->firstOrFail()->status)->toBe('accepted');
    app(TenantContext::class)->clear();
});

it('refuses to accept an expired invitation', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $invitation = Invitation::create([
        'email' => 'expired@example.com', 'role_id' => $role->id, 'token' => 'expired-token-123',
        'invited_by' => $this->admin->id, 'status' => 'pending', 'expires_at' => now()->subDay(),
    ]);
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)('/api/v1/invitations/expired-token-123/accept'), [
        'name' => 'Too Late', 'password' => 'Passw0rd123', 'password_confirmation' => 'Passw0rd123',
    ])->assertStatus(422);
});

it('does not resolve another tenant\'s invitation token', function () {
    Notification::fake();

    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Co 2');

    app(TenantContext::class)->set($this->tenant);
    $role = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    Invitation::create([
        'email' => 'crosstenant@example.com', 'role_id' => $role->id, 'token' => 'cross-tenant-token',
        'invited_by' => $this->admin->id, 'status' => 'pending', 'expires_at' => now()->addDays(7),
    ]);
    app(TenantContext::class)->clear();

    $otherHostname = $otherTenant->domains()->first()->hostname;
    $this->getJson("http://{$otherHostname}/api/v1/invitations/cross-tenant-token")->assertStatus(404);
});

// --- Role changes + deactivation -----------------------------------------

it('lets an admin change another member\'s role', function () {
    $student = makeUserWithRole($this->tenant, 'student');

    app(TenantContext::class)->set($this->tenant);
    $instructorRole = Role::forTenant($this->tenant->id)->where('slug', 'instructor')->firstOrFail();
    app(TenantContext::class)->clear();

    $this->putJson(($this->url)("/api/v1/admin/tenant-users/{$student->id}/role"), [
        'role_id' => $instructorRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $student->id)->firstOrFail()->role->slug)->toBe('instructor');
    app(TenantContext::class)->clear();
});

it('refuses to let an admin change their own role', function () {
    app(TenantContext::class)->set($this->tenant);
    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    app(TenantContext::class)->clear();

    $this->putJson(($this->url)("/api/v1/admin/tenant-users/{$this->admin->id}/role"), [
        'role_id' => $studentRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertStatus(422);
});

it('lets an admin deactivate and reactivate another member', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->deleteJson(($this->url)("/api/v1/admin/tenant-users/{$student->id}"), [], $headers)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $student->id)->firstOrFail()->status)->toBe('inactive');
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)("/api/v1/admin/tenant-users/{$student->id}/reactivate"), [], $headers)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $student->id)->firstOrFail()->status)->toBe('active');
    app(TenantContext::class)->clear();
});

it('refuses to let an admin deactivate themselves', function () {
    $this->deleteJson(($this->url)("/api/v1/admin/tenant-users/{$this->admin->id}"), [], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(422);
});

it('refuses a student access to user-management endpoints', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson(($this->url)('/api/v1/admin/tenant-users'), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
    $this->postJson(($this->url)('/api/v1/admin/invitations'), [], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});
