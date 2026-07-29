<?php

use App\Domain\Identity\Models\Invitation;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Identity\Notifications\AddedToTenantNotification;
use App\Domain\Identity\Notifications\TenantInvitationNotification;
use App\Domain\Tenancy\Services\TenantContext;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('sends a real invitation instead of fabricating a password when a platform admin provisions a tenant with a new owner email', function () {
    Notification::fake();

    $token = makePlatformStaffToken('platform-super-administrator');

    $response = $this->postJson('/api/v1/platform/tenants', [
        'name' => 'Acme Academy',
        'owner_email' => 'owner@acme.example',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $tenant = \App\Domain\Tenancy\Models\Tenant::find($response->json('data.id'));

    expect($tenant->owner_user_id)->toBeNull();
    expect(User::where('email', 'owner@acme.example')->exists())->toBeFalse();

    Notification::assertSentOnDemand(TenantInvitationNotification::class);

    app(TenantContext::class)->set($tenant);
    $invitation = Invitation::where('email', 'owner@acme.example')->where('status', 'pending')->firstOrFail();
    expect($invitation->role->slug)->toBe('tenant-owner');
    expect($invitation->invited_by)->not->toBeNull();
    app(TenantContext::class)->clear();
});

it('adds an existing user directly as owner instead of inviting them again', function () {
    Notification::fake();

    $existingUser = User::factory()->create(['email' => 'already@example.com']);
    $token = makePlatformStaffToken('platform-super-administrator');

    $response = $this->postJson('/api/v1/platform/tenants', [
        'name' => 'Beta Academy',
        'owner_email' => 'already@example.com',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $tenant = \App\Domain\Tenancy\Models\Tenant::find($response->json('data.id'));

    expect($tenant->owner_user_id)->toBe($existingUser->id);
    Notification::assertSentOnDemand(AddedToTenantNotification::class);

    app(TenantContext::class)->set($tenant);
    expect(TenantUser::where('user_id', $existingUser->id)->where('status', 'active')->exists())->toBeTrue();
    expect(Invitation::count())->toBe(0);
    app(TenantContext::class)->clear();
});

it('sets the tenant owner once the owner invitation is accepted', function () {
    Notification::fake();

    $token = makePlatformStaffToken('platform-super-administrator');

    $response = $this->postJson('/api/v1/platform/tenants', [
        'name' => 'Gamma Academy',
        'owner_email' => 'owner@gamma.example',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $tenant = \App\Domain\Tenancy\Models\Tenant::find($response->json('data.id'));

    app(TenantContext::class)->set($tenant);
    $invitationToken = Invitation::where('email', 'owner@gamma.example')->firstOrFail()->token;
    $hostname = $tenant->domains()->first()->hostname;
    app(TenantContext::class)->clear();

    // The prior authenticated request cached a resolved guard/user on the
    // shared test container; without clearing it, this next unauthenticated
    // request would incorrectly inherit that platform-staff identity. See
    // AuthTest.php/PlatformAuthTest.php for the same pattern.
    $this->app->make('auth')->forgetGuards();

    $accept = $this->postJson("http://{$hostname}/api/v1/invitations/{$invitationToken}/accept", [
        'name' => 'Gamma Owner', 'password' => 'Passw0rd123', 'password_confirmation' => 'Passw0rd123',
    ])->assertCreated();

    expect($accept->json('data.token'))->not->toBeNull();

    $owner = User::where('email', 'owner@gamma.example')->firstOrFail();
    expect($tenant->fresh()->owner_user_id)->toBe($owner->id);

    app(TenantContext::class)->set($tenant);
    $member = TenantUser::where('user_id', $owner->id)->firstOrFail();
    expect(Role::find($member->role_id)->slug)->toBe('tenant-owner');
    app(TenantContext::class)->clear();
});

it('provisions a tenant with no owner and no invitation when owner_email is omitted', function () {
    Notification::fake();

    $token = makePlatformStaffToken('platform-super-administrator');

    $response = $this->postJson('/api/v1/platform/tenants', [
        'name' => 'No Owner Yet',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $tenant = \App\Domain\Tenancy\Models\Tenant::find($response->json('data.id'));
    expect($tenant->owner_user_id)->toBeNull();

    Notification::assertNothingSent();
});
