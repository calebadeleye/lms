<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Testimony Co');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    app(TenantContext::class)->set($this->tenant);
    $ownerRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-owner')->firstOrFail();
    $this->owner = User::factory()->create();
    TenantUser::create(['user_id' => $this->owner->id, 'role_id' => $ownerRole->id, 'status' => 'active', 'joined_at' => now()]);

    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $this->student = User::factory()->create();
    TenantUser::create(['user_id' => $this->student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    $this->ownerToken = $this->owner->createToken('test')->plainTextToken;
    $this->studentToken = $this->student->createToken('test')->plainTextToken;
});

it('lets a tenant owner create a testimonial', function () {
    $response = $this->postJson(($this->url)('/api/v1/tenant/testimonials'), [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertCreated();

    expect($response->json('data.quote'))->toBe('This platform changed how our team learns.');
    expect($response->json('data.author'))->toBe('Ada, L&D Lead');

    $this->getJson(($this->url)('/api/v1/tenant/testimonials'), [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk()->assertJsonCount(1, 'data');
});

it('exposes created testimonials on the public tenant-config endpoint', function () {
    $this->postJson(($this->url)('/api/v1/tenant/testimonials'), [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertCreated();

    $testimonials = $this->getJson(($this->url)('/api/v1/tenant-config'))
        ->assertOk()
        ->json('data.testimonials');

    expect($testimonials)->toHaveCount(1);
    expect($testimonials[0]['author'])->toBe('Ada, L&D Lead');
});

it('lets a tenant owner delete a testimonial, and it disappears from tenant-config immediately', function () {
    $created = $this->postJson(($this->url)('/api/v1/tenant/testimonials'), [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertCreated()->json('data');

    $this->deleteJson(($this->url)("/api/v1/tenant/testimonials/{$created['id']}"), [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    $testimonials = $this->getJson(($this->url)('/api/v1/tenant-config'))
        ->assertOk()
        ->json('data.testimonials');

    expect($testimonials)->toBeEmpty();
});

it('rejects testimonial creation from a user without branding.manage', function () {
    $this->postJson(($this->url)('/api/v1/tenant/testimonials'), [
        'quote' => 'This platform changed how our team learns.',
        'author' => 'Ada, L&D Lead',
    ], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('requires both quote and author', function () {
    $this->postJson(($this->url)('/api/v1/tenant/testimonials'), [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertStatus(422);
});
