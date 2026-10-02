<?php

use App\Domain\Learning\Models\Course;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('uploads');
    $this->seed(PermissionSeeder::class);

    $this->admin = makeUserWithRole('administrator');
    $this->student = makeUserWithRole('student');

    $this->course = Course::create([
        'title' => 'Thumbnail Course', 'slug' => 'thumbnail-course', 'status' => 'published',
        'pricing_type' => 'free', 'published_at' => now(),
    ]);

    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
    $this->studentToken = $this->student->createToken('t')->plainTextToken;
});

it('lets an admin upload a course thumbnail, retrievable via the public asset route', function () {
    $file = UploadedFile::fake()->image('cover.jpg', 400, 225);

    $response = $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", ['file' => $file], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    $url = $response->json('data.thumbnail_url');
    expect($url)->toStartWith("/api/v1/course-assets/{$this->course->id}/thumbnail.jpg?v=");

    Storage::disk('uploads')->assertExists("courses/{$this->course->id}/thumbnail.jpg");
    $this->get("/api/v1/course-assets/{$this->course->id}/thumbnail.jpg")->assertOk();
});

it('replaces the old thumbnail file when a new one is uploaded in a different format', function () {
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", [
        'file' => UploadedFile::fake()->image('cover.png', 400, 225),
    ], $headers)->assertOk();
    Storage::disk('uploads')->assertExists("courses/{$this->course->id}/thumbnail.png");

    $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", [
        'file' => UploadedFile::fake()->image('cover.jpg', 400, 225),
    ], $headers)->assertOk();

    Storage::disk('uploads')->assertMissing("courses/{$this->course->id}/thumbnail.png");
    Storage::disk('uploads')->assertExists("courses/{$this->course->id}/thumbnail.jpg");
});

it('exposes the thumbnail url on the public catalogue, detail, and admin list endpoints', function () {
    $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", [
        'file' => UploadedFile::fake()->image('cover.jpg', 400, 225),
    ], ['Authorization' => "Bearer {$this->adminToken}"])->assertOk();

    $catalogue = $this->getJson('/api/v1/courses')->assertOk();
    $row = collect($catalogue->json('data'))->firstWhere('id', $this->course->id);
    expect($row['thumbnail_url'])->toContain('/course-assets/');

    $detail = $this->getJson("/api/v1/courses/{$this->course->slug}")->assertOk();
    expect($detail->json('data.thumbnail_url'))->toContain('/course-assets/');

    $adminList = $this->getJson('/api/v1/admin/courses', ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();
    $adminRow = collect($adminList->json('data'))->firstWhere('id', $this->course->id);
    expect($adminRow['thumbnail_url'])->toContain('/course-assets/');
});

it('rejects a thumbnail upload from a student', function () {
    $file = UploadedFile::fake()->image('cover.jpg', 400, 225);

    $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", ['file' => $file], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);
});

it('rejects a disallowed file type for the thumbnail upload', function () {
    $file = UploadedFile::fake()->create('script.exe', 10);

    $this->postJson("/api/v1/admin/courses/{$this->course->id}/thumbnail", ['file' => $file], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(422);
});

it('404s the public course-asset route for a nonexistent file', function () {
    $this->get("/api/v1/course-assets/{$this->course->id}/thumbnail.jpg")->assertStatus(404);
});

// --- Pexels fallback thumbnail, filled in at publish time ---------------

it('fills in a Pexels stock photo when a course is published with no thumbnail of its own', function () {
    config(['services.pexels.key' => 'test_key']);
    Http::fake([
        'api.pexels.co/*' => Http::response([], 404), // safety net: never match by accident
        'api.pexels.com/v1/search*' => Http::response([
            'photos' => [['src' => ['medium' => 'https://images.pexels.com/photos/1/pic.jpeg']]],
        ], 200),
        'images.pexels.com/*' => Http::response('fake-jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $draft = Course::create(['title' => 'HR Analytics for Data-Driven Decisions', 'slug' => 'hra-draft', 'status' => 'draft']);

    $this->postJson("/api/v1/admin/courses/{$draft->id}/publish", [], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();

    $draft->refresh();
    expect($draft->thumbnail_path)->toBe("courses/{$draft->id}/thumbnail.jpg");
    Storage::disk('uploads')->assertExists($draft->thumbnail_path);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.pexels.com/v1/search')
        && $request['query'] === 'HR Analytics for Data-Driven Decisions');
});

it('leaves an existing thumbnail alone when publishing, even with Pexels configured', function () {
    config(['services.pexels.key' => 'test_key']);
    Http::fake(); // if this fires at all, Http::assertNotSent below catches it

    $draft = Course::create([
        'title' => 'Already Has A Thumbnail', 'slug' => 'already-thumb', 'status' => 'draft',
        'thumbnail_path' => 'courses/999/thumbnail.jpg',
    ]);

    $this->postJson("/api/v1/admin/courses/{$draft->id}/publish", [], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();

    expect($draft->fresh()->thumbnail_path)->toBe('courses/999/thumbnail.jpg');
    Http::assertNothingSent();
});

it('publishes successfully even when Pexels is unreachable', function () {
    config(['services.pexels.key' => 'test_key']);
    Http::fake(['api.pexels.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);

    $draft = Course::create(['title' => 'Unreachable Pexels Course', 'slug' => 'unreachable-pexels', 'status' => 'draft']);

    $this->postJson("/api/v1/admin/courses/{$draft->id}/publish", [], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();

    expect($draft->fresh()->status)->toBe('published');
    expect($draft->fresh()->thumbnail_path)->toBeNull();
});

it('does not call Pexels at all when no key is configured', function () {
    config(['services.pexels.key' => null]);
    Http::fake();

    $draft = Course::create(['title' => 'No Pexels Key Course', 'slug' => 'no-pexels-key', 'status' => 'draft']);

    $this->postJson("/api/v1/admin/courses/{$draft->id}/publish", [], ['Authorization' => "Bearer {$this->adminToken}"])
        ->assertOk();

    Http::assertNothingSent();
    expect($draft->fresh()->thumbnail_path)->toBeNull();
});
