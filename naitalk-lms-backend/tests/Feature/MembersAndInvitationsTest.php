<?php

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Notifications\AddedAsMemberNotification;
use App\Domain\Identity\Notifications\MemberInvitationNotification;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = makeUserWithRole('administrator');
    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
});

// --- Roles + member listing --------------------------------------------

it('lists assignable roles', function () {
    $response = $this->getJson('/api/v1/admin/roles', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->toContain('student', 'instructor');
});

it('only lists active members by default, and everyone with include_inactive', function () {
    $student = makeUserWithRole('student');
    $student->update(['status' => 'inactive']);

    $default = $this->getJson('/api/v1/admin/members', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();
    expect(collect($default->json('data'))->pluck('id'))->not->toContain($student->id);

    $all = $this->getJson('/api/v1/admin/members?include_inactive=1', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();
    expect(collect($all->json('data'))->pluck('id'))->toContain($student->id);
});

it('returns the full unpaginated array when no page param is given, for pickers', function () {
    makeUserWithRole('student');
    makeUserWithRole('instructor');

    $response = $this->getJson('/api/v1/admin/members', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect($response->json('meta'))->toBeNull();
    expect(is_array($response->json('data')))->toBeTrue();
});

it('paginates and reports meta when a page param is given', function () {
    for ($i = 0; $i < 3; $i++) {
        makeUserWithRole('student');
    }

    $response = $this->getJson('/api/v1/admin/members?page=1&per_page=2', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('meta.pagination.per_page'))->toBe(2);
    expect($response->json('meta.pagination.total'))->toBeGreaterThanOrEqual(4);
});

it('searches members by name or email', function () {
    $match = makeUserWithRole('student');
    $match->update(['name' => 'Zanele Distinct', 'email' => 'zanele@example.com']);

    $response = $this->getJson('/api/v1/admin/members?search=Zanele', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($match->id);
    expect(collect($response->json('data')))->toHaveCount(1);
});

// --- Inviting ------------------------------------------------------------

it('invites a brand-new email and sends an invitation notification', function () {
    Notification::fake();

    $instructorRole = Role::where('slug', 'instructor')->firstOrFail();

    $response = $this->postJson('/api/v1/admin/invitations', [
        'email' => 'newperson@example.com', 'role_id' => $instructorRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    expect($response->json('data.email'))->toBe('newperson@example.com');

    Notification::assertSentOnDemand(MemberInvitationNotification::class);

    expect(Invitation::where('email', 'newperson@example.com')->where('status', 'pending')->exists())->toBeTrue();
});

it('adds an existing user directly instead of creating an invitation', function () {
    Notification::fake();

    $existingUser = User::factory()->create(['email' => 'already@example.com']);

    $coachRole = Role::where('slug', 'coach')->firstOrFail();

    $response = $this->postJson('/api/v1/admin/invitations', [
        'email' => 'already@example.com', 'role_id' => $coachRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    expect($response->json('data.status'))->toBe('added_existing_user');
    Notification::assertSentOnDemand(AddedAsMemberNotification::class);

    expect($existingUser->fresh()->status)->toBe('active');
    expect($existingUser->fresh()->role->slug)->toBe('coach');
    expect(Invitation::count())->toBe(0);
});

it('refuses a second invitation while one is already pending for the same email', function () {
    Notification::fake();

    $role = Role::where('slug', 'student')->firstOrFail();

    $payload = ['email' => 'dup@example.com', 'role_id' => $role->id];
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->postJson('/api/v1/admin/invitations', $payload, $headers)->assertCreated();
    $this->postJson('/api/v1/admin/invitations', $payload, $headers)->assertStatus(422);
});

it('lets an admin revoke a pending invitation', function () {
    Notification::fake();

    $role = Role::where('slug', 'student')->firstOrFail();

    $headers = ['Authorization' => "Bearer {$this->adminToken}"];
    $created = $this->postJson('/api/v1/admin/invitations', [
        'email' => 'revoke-me@example.com', 'role_id' => $role->id,
    ], $headers)->assertCreated();

    $this->deleteJson("/api/v1/admin/invitations/{$created->json('data.id')}", [], $headers)->assertOk();

    $pending = $this->getJson('/api/v1/admin/invitations', $headers)->assertOk();
    expect(collect($pending->json('data'))->pluck('email'))->not->toContain('revoke-me@example.com');
});

// --- Accepting -------------------------------------------------------------

it('previews a pending invitation publicly', function () {
    Notification::fake();

    $role = Role::where('slug', 'instructor')->firstOrFail();

    $this->postJson('/api/v1/admin/invitations', [
        'email' => 'previewer@example.com', 'role_id' => $role->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    $token = Invitation::where('email', 'previewer@example.com')->firstOrFail()->token;

    $preview = $this->getJson("/api/v1/invitations/{$token}")->assertOk();
    expect($preview->json('data.email'))->toBe('previewer@example.com');
    expect($preview->json('data.role_name'))->toBe('Instructor');
});

it('accepts an invitation, creating a verified, immediately-usable account', function () {
    Notification::fake();

    $role = Role::where('slug', 'instructor')->firstOrFail();

    $this->postJson('/api/v1/admin/invitations', [
        'email' => 'accepter@example.com', 'role_id' => $role->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertCreated();

    $token = Invitation::where('email', 'accepter@example.com')->firstOrFail()->token;

    $response = $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'Accepter Person', 'password' => 'Passw0rd123', 'password_confirmation' => 'Passw0rd123',
    ])->assertCreated();

    expect($response->json('data.token'))->not->toBeNull();

    $user = User::where('email', 'accepter@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->role->slug)->toBe('instructor');
    expect($user->status)->toBe('active');
    expect(Invitation::where('email', 'accepter@example.com')->firstOrFail()->status)->toBe('accepted');
});

it('refuses to accept an expired invitation', function () {
    Notification::fake();

    $role = Role::where('slug', 'student')->firstOrFail();
    Invitation::create([
        'email' => 'expired@example.com', 'role_id' => $role->id, 'token' => 'expired-token-123',
        'invited_by' => $this->admin->id, 'status' => 'pending', 'expires_at' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/invitations/expired-token-123/accept', [
        'name' => 'Too Late', 'password' => 'Passw0rd123', 'password_confirmation' => 'Passw0rd123',
    ])->assertStatus(422);
});

// --- Role changes + deactivation -----------------------------------------

it('lets an admin change another member\'s role', function () {
    $student = makeUserWithRole('student');

    $instructorRole = Role::where('slug', 'instructor')->firstOrFail();

    $this->putJson("/api/v1/admin/members/{$student->id}/role", [
        'role_id' => $instructorRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertOk();

    expect($student->fresh()->role->slug)->toBe('instructor');
});

it('refuses to let an admin change their own role', function () {
    $studentRole = Role::where('slug', 'student')->firstOrFail();

    $this->putJson("/api/v1/admin/members/{$this->admin->id}/role", [
        'role_id' => $studentRole->id,
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertStatus(422);
});

it('lets an admin deactivate and reactivate another member', function () {
    $student = makeUserWithRole('student');
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->deleteJson("/api/v1/admin/members/{$student->id}", [], $headers)->assertOk();
    expect($student->fresh()->status)->toBe('inactive');

    $this->postJson("/api/v1/admin/members/{$student->id}/reactivate", [], $headers)->assertOk();
    expect($student->fresh()->status)->toBe('active');
});

it('refuses to let an admin deactivate themselves', function () {
    $this->deleteJson("/api/v1/admin/members/{$this->admin->id}", [], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(422);
});

it('refuses a student access to user-management endpoints', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/admin/members', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
    $this->postJson('/api/v1/admin/invitations', [], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});
