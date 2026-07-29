<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Course;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tenants');
    $this->seed(PermissionSeeder::class);

    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Thumb Co');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    app(TenantContext::class)->set($this->tenant);
    $adminRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-administrator')->firstOrFail();
    $this->admin = User::factory()->create();
    TenantUser::create(['user_id' => $this->admin->id, 'role_id' => $adminRole->id, 'status' => 'active', 'joined_at' => now()]);

    $studentRole = Role::forTenant($this->tenant->id)->where('slug', 'student')->firstOrFail();
    $this->student = User::factory()->create();
    TenantUser::create(['user_id' => $this->student->id, 'role_id' => $studentRole->id, 'status' => 'active', 'joined_at' => now()]);

    $this->course = Course::create([
        'title' => 'Thumbnail Course', 'slug' => 'thumbnail-course', 'status' => 'published',
        'pricing_type' => 'free', 'published_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
    $this->studentToken = $this->student->createToken('t')->plainTextToken;
});

it('lets an admin upload a course thumbnail, retrievable via the public asset route', function () {
    $file = UploadedFile::fake()->image('cover.jpg', 400, 225);

    $response = $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), ['file' => $file], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    $url = $response->json('data.thumbnail_url');
    expect($url)->toStartWith("/api/v1/course-assets/{$this->tenant->id}/{$this->course->id}/thumbnail.jpg?v=");

    Storage::disk('tenants')->assertExists("{$this->tenant->id}/courses/{$this->course->id}/thumbnail.jpg");
    $this->get("/api/v1/course-assets/{$this->tenant->id}/{$this->course->id}/thumbnail.jpg")->assertOk();
});

it('replaces the old thumbnail file when a new one is uploaded in a different format', function () {
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), [
        'file' => UploadedFile::fake()->image('cover.png', 400, 225),
    ], $headers)->assertOk();
    Storage::disk('tenants')->assertExists("{$this->tenant->id}/courses/{$this->course->id}/thumbnail.png");

    $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), [
        'file' => UploadedFile::fake()->image('cover.jpg', 400, 225),
    ], $headers)->assertOk();

    Storage::disk('tenants')->assertMissing("{$this->tenant->id}/courses/{$this->course->id}/thumbnail.png");
    Storage::disk('tenants')->assertExists("{$this->tenant->id}/courses/{$this->course->id}/thumbnail.jpg");
});

it('exposes the thumbnail url on the public catalogue, detail, and admin list endpoints', function () {
    $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), [
        'file' => UploadedFile::fake()->image('cover.jpg', 400, 225),
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertOk();

    $catalogue = $this->getJson(($this->url)('/api/v1/courses'))->assertOk();
    $row = collect($catalogue->json('data'))->firstWhere('id', $this->course->id);
    expect($row['thumbnail_url'])->toContain('/course-assets/');

    $detail = $this->getJson(($this->url)("/api/v1/courses/{$this->course->slug}"))->assertOk();
    expect($detail->json('data.thumbnail_url'))->toContain('/course-assets/');

    $adminList = $this->getJson(($this->url)('/api/v1/admin/courses'), ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();
    $adminRow = collect($adminList->json('data'))->firstWhere('id', $this->course->id);
    expect($adminRow['thumbnail_url'])->toContain('/course-assets/');
});

it('rejects a thumbnail upload from a student', function () {
    $file = UploadedFile::fake()->image('cover.jpg', 400, 225);

    $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), ['file' => $file], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('rejects a disallowed file type for the thumbnail upload', function () {
    $file = UploadedFile::fake()->create('script.exe', 10);

    $this->postJson(($this->url)("/api/v1/admin/courses/{$this->course->id}/thumbnail"), ['file' => $file], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(422);
});

it('404s the public course-asset route for a nonexistent file', function () {
    $this->get("/api/v1/course-assets/{$this->tenant->id}/{$this->course->id}/thumbnail.jpg")->assertStatus(404);
});
