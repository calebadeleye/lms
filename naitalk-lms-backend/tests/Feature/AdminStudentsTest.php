<?php

use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\EnrolmentService;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Roster Co');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    $this->admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
});

it('lists students tenant-wide with aggregate enrolment and completion counts', function () {
    $student = makeUserWithRole($this->tenant, 'student');

    app(TenantContext::class)->set($this->tenant);
    $course = Course::create([
        'title' => 'Course A', 'slug' => 'course-a', 'status' => 'published',
        'pricing_type' => 'free', 'published_at' => now(),
    ]);
    app(EnrolmentService::class)->enroll($student, $course, 'free');
    app(TenantContext::class)->clear();

    $response = $this->getJson(($this->url)('/api/v1/admin/students'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $student->id);
    expect($row)->not->toBeNull();
    expect($row['enrolments_count'])->toBe(1);
    expect($row['completed_count'])->toBe(0);
});

it('excludes instructors and other staff from the student roster', function () {
    $instructor = makeUserWithRole($this->tenant, 'instructor');

    $response = $this->getJson(($this->url)('/api/v1/admin/students'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($instructor->id);
});

it('lets students.manage deactivate and reactivate a student', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->deleteJson(($this->url)("/api/v1/admin/students/{$student->id}"), [], $headers)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $student->id)->firstOrFail()->status)->toBe('inactive');
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)("/api/v1/admin/students/{$student->id}/reactivate"), [], $headers)->assertOk();

    app(TenantContext::class)->set($this->tenant);
    expect(TenantUser::where('user_id', $student->id)->firstOrFail()->status)->toBe('active');
    app(TenantContext::class)->clear();
});

it('refuses to deactivate a non-student user through the students endpoint', function () {
    $instructor = makeUserWithRole($this->tenant, 'instructor');

    $this->deleteJson(($this->url)("/api/v1/admin/students/{$instructor->id}"), [], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(404);
});

it('paginates the roster and reports pagination meta', function () {
    for ($i = 0; $i < 3; $i++) {
        makeUserWithRole($this->tenant, 'student');
    }

    $response = $this->getJson(($this->url)('/api/v1/admin/students?page=1&per_page=2'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('meta.pagination.total'))->toBeGreaterThanOrEqual(3);
});

it('searches students by name or email', function () {
    $match = makeUserWithRole($this->tenant, 'student');
    $match->update(['name' => 'Obiora Findable', 'email' => 'obiora@example.com']);
    makeUserWithRole($this->tenant, 'student');

    $response = $this->getJson(($this->url)('/api/v1/admin/students?search=obiora'), [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($match->id);
    expect(collect($response->json('data')))->toHaveCount(1);
});

it('refuses a student access to the roster endpoint', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson(($this->url)('/api/v1/admin/students'), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});
