<?php

use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('logs a platform staff member out and revokes the token used', function () {
    $token = makePlatformStaffToken();

    // The token must exist and work before logout.
    $this->getJson('/api/v1/platform/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->postJson('/api/v1/platform/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

it('refuses a revoked platform token on a subsequent request', function () {
    $token = makePlatformStaffToken();

    $this->postJson('/api/v1/platform/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

    // Sanctum's RequestGuard caches the resolved user for the container's
    // lifetime — without this, the second call below would reuse the
    // guard's cached resolution from the login/logout calls above instead
    // of re-resolving (and failing to resolve) the now-deleted token.
    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/v1/platform/auth/me', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
});
