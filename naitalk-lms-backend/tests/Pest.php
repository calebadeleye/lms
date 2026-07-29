<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

function actingAsTenantUser(Tenant $tenant, User $user, ?string $roleSlug = null): User
{
    $role = Role::query()
        ->where('tenant_id', $tenant->id)
        ->when($roleSlug, fn ($q) => $q->where('slug', $roleSlug))
        ->firstOrFail();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role_id' => $role->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $user;
}

/** Creates a fresh user and gives them the named tenant role in one call —
 * the pattern nearly every Phase 2/3 feature test needs (a student to act
 * as, an admin/finance/content-manager to test a permission-gated action). */
function makeUserWithRole(Tenant $tenant, string $roleSlug): User
{
    app(\App\Domain\Tenancy\Services\TenantContext::class)->set($tenant);
    $user = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    actingAsTenantUser($tenant, $user, $roleSlug);
    app(\App\Domain\Tenancy\Services\TenantContext::class)->clear();

    return $user;
}

/** Creates a fresh platform-staff user with the named role and returns a
 * ready-to-use bearer token — platform routes never resolve a
 * TenantContext, so this never touches it. */
function makePlatformStaffToken(string $roleSlug = 'platform-super-administrator'): string
{
    $staffUser = User::factory()->create(['password' => bcrypt('Passw0rd123')]);
    $staffRole = Role::platform()->where('slug', $roleSlug)->firstOrFail();
    \App\Domain\Identity\Models\PlatformStaff::create([
        'user_id' => $staffUser->id, 'role_id' => $staffRole->id, 'status' => 'active',
    ]);

    return test()->postJson('/api/v1/platform/auth/login', [
        'email' => $staffUser->email, 'password' => 'Passw0rd123',
    ])->assertOk()->json('data.token');
}

/** Symfony's Request::create() re-derives HTTP_HOST from the URI itself
 * when the URI has a host component, clobbering any separately-passed Host
 * header — so tenant-domain tests must hit a full tenant URL rather than a
 * relative path + ['Host' => ...] header. See Phase 1's AuthTest.php for
 * where this was first worked out. */
function tenantUrl(Tenant $tenant, string $path): string
{
    $hostname = $tenant->domains()->first()->hostname;

    return "http://{$hostname}{$path}";
}
