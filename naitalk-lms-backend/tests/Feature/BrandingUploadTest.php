<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tenants');
    $this->seed(PermissionSeeder::class);

    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Brand Co');
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

it('lets a tenant owner upload a logo, and it becomes retrievable via the public asset route', function () {
    $file = UploadedFile::fake()->image('mylogo.png', 200, 200);

    $response = $this->postJson(($this->url)('/api/v1/tenant/branding/logo'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    expect($response->json('data.logo_path'))->toBe("{$this->tenant->id}/branding/logo.png");
    Storage::disk('tenants')->assertExists("{$this->tenant->id}/branding/logo.png");

    $this->get("/api/v1/tenant-assets/branding/{$this->tenant->id}/logo.png")->assertOk();
});

it('exposes the uploaded logo as a root-relative, cache-busted URL from tenant-config', function () {
    $file = UploadedFile::fake()->image('mylogo.png', 200, 200);

    $this->postJson(($this->url)('/api/v1/tenant/branding/logo'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    // Deliberately root-relative, not an absolute URL built from the
    // request's own Host — the browser only ever reaches the backend
    // through the frontend's own origin (a Server Component fetch or the
    // client-side BFF proxy), never directly, so an absolute URL baked
    // from whichever internal address issued the request that populated
    // this cache (e.g. the BFF's own loopback address) would be
    // unreachable from an actual visitor's browser.
    $logoUrl = $this->getJson(($this->url)('/api/v1/tenant-config'))
        ->assertOk()
        ->json('data.branding.logo_url');

    expect($logoUrl)->toStartWith("/api/v1/tenant-assets/branding/{$this->tenant->id}/logo.png?v=");
});

it('lets a tenant owner upload a favicon', function () {
    $file = UploadedFile::fake()->image('icon.png', 32, 32);

    $response = $this->postJson(($this->url)('/api/v1/tenant/branding/favicon'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    expect($response->json('data.favicon_path'))->toBe("{$this->tenant->id}/branding/favicon.png");
});

it('lets a tenant owner upload a hero image, and it becomes retrievable via the public asset route', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 1600, 1200);

    $response = $this->postJson(($this->url)('/api/v1/tenant/branding/hero-image'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    expect($response->json('data.hero_image_path'))->toBe("{$this->tenant->id}/branding/hero.jpg");
    Storage::disk('tenants')->assertExists("{$this->tenant->id}/branding/hero.jpg");

    $this->get("/api/v1/tenant-assets/branding/{$this->tenant->id}/hero.jpg")->assertOk();
});

it('exposes the uploaded hero image as a root-relative, cache-busted URL from tenant-config', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 1600, 1200);

    $this->postJson(($this->url)('/api/v1/tenant/branding/hero-image'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    $heroUrl = $this->getJson(($this->url)('/api/v1/tenant-config'))
        ->assertOk()
        ->json('data.branding.hero_image_url');

    expect($heroUrl)->toStartWith("/api/v1/tenant-assets/branding/{$this->tenant->id}/hero.jpg?v=");
});

it('rejects a hero image upload from a user without branding.manage', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 1600, 1200);

    $this->postJson(($this->url)('/api/v1/tenant/branding/hero-image'), ['file' => $file], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('rejects a logo upload from a user without branding.manage', function () {
    $file = UploadedFile::fake()->image('mylogo.png', 200, 200);

    $this->postJson(($this->url)('/api/v1/tenant/branding/logo'), ['file' => $file], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('rejects a disallowed file type for the logo upload', function () {
    $file = UploadedFile::fake()->create('script.exe', 10);

    $this->postJson(($this->url)('/api/v1/tenant/branding/logo'), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertStatus(422);
});

it('404s the public asset route for a nonexistent file and cannot reach other files on the disk', function () {
    $this->get("/api/v1/tenant-assets/branding/{$this->tenant->id}/logo.png")->assertStatus(404);

    // The route pattern itself only matches `logo.*`/`favicon.*` filenames —
    // anything else, including an attempted traversal, doesn't even match
    // the route and falls through to a plain 404.
    $this->get("/api/v1/tenant-assets/branding/{$this->tenant->id}/..%2f..%2fexports%2fsecret.zip")->assertStatus(404);
});
