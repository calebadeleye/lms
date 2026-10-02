<?php

use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/** Creates a fresh, already-approved (status=active) user with the named
 * role — the pattern nearly every feature test needs (a student to act as,
 * an admin/finance/content-manager to test a permission-gated action).
 * Callers testing the onboarding/approval-gate flow itself should pass
 * $status explicitly instead (e.g. 'pending', 'rejected'). */
function makeUserWithRole(string $roleSlug, string $status = 'active'): User
{
    $role = Role::where('slug', $roleSlug)->firstOrFail();

    return User::factory()->create([
        'password' => bcrypt('Passw0rd123'),
        'role_id' => $role->id,
        'status' => $status,
        'joined_at' => now(),
    ]);
}
