<?php

use App\Domain\Billing\Models\TenantSubscription;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformPlanSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PlatformPlanSeeder::class);

    $this->tenantA = app(TenantProvisioningService::class)->provision(name: 'Tenant A');
    $this->tenantB = app(TenantProvisioningService::class)->provision(name: 'Tenant B');
});

it('resolves a 404 for a hostname with no verified tenant domain', function () {
    $this->getJson('http://nobody-owns-this.naitalk-lms.test/api/v1/tenant-config')
        ->assertStatus(404)
        ->assertJsonPath('errors.0.code', 'tenant_not_found');
});

it('resolves the correct tenant from its verified hostname', function () {
    $hostA = $this->tenantA->domains()->first()->hostname;

    $this->getJson("http://{$hostA}/api/v1/tenant-config")
        ->assertOk()
        ->assertJsonPath('data.tenant.id', $this->tenantA->id);
});

it('does not allow one tenant to log in on another tenant\'s domain', function () {
    $hostA = $this->tenantA->domains()->first()->hostname;
    $hostB = $this->tenantB->domains()->first()->hostname;

    app(TenantContext::class)->set($this->tenantB);
    $studentRole = Role::forTenant($this->tenantB->id)->where('slug', 'student')->firstOrFail();
    $user = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    TenantUser::create(['user_id' => $user->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    // Correct domain: succeeds.
    $this->postJson("http://{$hostB}/api/v1/auth/login", [
        'email' => $user->email, 'password' => 'Passw0rd123',
    ])->assertOk();

    // Wrong tenant's domain: same credentials, must be rejected.
    $this->postJson("http://{$hostA}/api/v1/auth/login", [
        'email' => $user->email, 'password' => 'Passw0rd123',
    ])->assertStatus(422);
});

it('cannot read another tenant\'s branding through the tenant-scoped API', function () {
    $hostA = $this->tenantA->domains()->first()->hostname;

    app(TenantContext::class)->set($this->tenantA);
    $ownerRole = Role::forTenant($this->tenantA->id)->where('slug', 'tenant-owner')->firstOrFail();
    $userA = User::factory()->create();
    TenantUser::create(['user_id' => $userA->id, 'role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $userA->createToken('test')->plainTextToken;

    // Update tenant B's branding directly (bypassing HTTP) to a distinctive value.
    app(TenantContext::class)->set($this->tenantB);
    $this->tenantB->branding()->update(['email_sender_name' => 'Tenant B Secret Name']);
    app(TenantContext::class)->clear();

    // Tenant A's authenticated user, hitting tenant A's own domain, must
    // only ever see tenant A's branding — the global scope makes it
    // structurally impossible to fetch tenant B's row here regardless of ids.
    $response = $this->getJson("http://{$hostA}/api/v1/tenant/branding", [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data.email_sender_name'))->not->toBe('Tenant B Secret Name');
});

it('cannot access another tenant\'s domain record via route-model binding', function () {
    $hostA = $this->tenantA->domains()->first()->hostname;
    $domainB = $this->tenantB->domains()->first();

    app(TenantContext::class)->set($this->tenantA);
    $ownerRole = Role::forTenant($this->tenantA->id)->where('slug', 'tenant-owner')->firstOrFail();
    $userA = User::factory()->create();
    TenantUser::create(['user_id' => $userA->id, 'role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $token = $userA->createToken('test')->plainTextToken;

    // Tenant A's admin tries to verify tenant B's domain by id.
    $this->postJson("http://{$hostA}/api/v1/tenant/domains/{$domainB->id}/verify", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(404);
});

it('creates a complimentary subscription directly without any invoice concept', function () {
    $plan = \App\Domain\Billing\Models\PlatformPlan::where('code', 'professional-monthly')->firstOrFail();

    app(TenantContext::class)->set($this->tenantA);
    $subscription = TenantSubscription::create([
        'plan_id' => $plan->id,
        'status' => 'complimentary',
        'complimentary_reason' => 'Partnership pilot',
        'complimentary_until' => now()->addYear(),
    ]);
    app(TenantContext::class)->clear();

    expect($subscription->isComplimentary())->toBeTrue();
    expect($subscription->isUsable())->toBeTrue();
});

it('grants complimentary access to a tenant via the platform API without creating a paid invoice', function () {
    $staffUser = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    $staffRole = Role::platform()->where('slug', 'platform-super-administrator')->firstOrFail();
    \App\Domain\Identity\Models\PlatformStaff::create(['user_id' => $staffUser->id, 'role_id' => $staffRole->id, 'status' => 'active']);

    $token = $this->postJson('/api/v1/platform/auth/login', [
        'email' => $staffUser->email, 'password' => 'Passw0rd123',
    ])->assertOk()->json('data.token');

    $plan = \App\Domain\Billing\Models\PlatformPlan::where('code', 'professional-monthly')->firstOrFail();

    $response = $this->postJson("/api/v1/platform/tenants/{$this->tenantA->id}/subscription/complimentary", [
        'plan_id' => $plan->id,
        'reason' => 'Strategic partner',
    ], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect($response->json('data.status'))->toBe('complimentary');
    expect($response->json('data.complimentary_reason'))->toBe('Strategic partner');
});
