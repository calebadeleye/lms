<?php

use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Services\EnrolmentService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = makeUserWithRole('administrator');
    $this->adminToken = $this->admin->createToken('t')->plainTextToken;
});

it('lists students with aggregate enrolment and completion counts', function () {
    $student = makeUserWithRole('student');

    $course = Course::create([
        'title' => 'Course A', 'slug' => 'course-a', 'status' => 'published',
        'pricing_type' => 'free', 'published_at' => now(),
    ]);
    app(EnrolmentService::class)->enroll($student, $course, 'free');

    $response = $this->getJson('/api/v1/admin/students', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $student->id);
    expect($row)->not->toBeNull();
    expect($row['enrolments_count'])->toBe(1);
    expect($row['completed_count'])->toBe(0);
});

it('excludes instructors and other staff from the student roster', function () {
    $instructor = makeUserWithRole('instructor');

    $response = $this->getJson('/api/v1/admin/students', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($instructor->id);
});

it('lets students.manage deactivate and reactivate a student', function () {
    $student = makeUserWithRole('student');
    $headers = ['Authorization' => "Bearer {$this->adminToken}"];

    $this->deleteJson("/api/v1/admin/students/{$student->id}", [], $headers)->assertOk();
    expect($student->fresh()->status)->toBe('inactive');

    $this->postJson("/api/v1/admin/students/{$student->id}/reactivate", [], $headers)->assertOk();
    expect($student->fresh()->status)->toBe('active');
});

it('refuses to deactivate a non-student user through the students endpoint', function () {
    $instructor = makeUserWithRole('instructor');

    $this->deleteJson("/api/v1/admin/students/{$instructor->id}", [], [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertStatus(404);
});

it('paginates the roster and reports pagination meta', function () {
    for ($i = 0; $i < 3; $i++) {
        makeUserWithRole('student');
    }

    $response = $this->getJson('/api/v1/admin/students?page=1&per_page=2', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('meta.pagination.total'))->toBeGreaterThanOrEqual(3);
});

it('searches students by name or email', function () {
    $match = makeUserWithRole('student');
    $match->update(['name' => 'Obiora Findable', 'email' => 'obiora@example.com']);
    makeUserWithRole('student');

    $response = $this->getJson('/api/v1/admin/students?search=obiora', [
        'Authorization' => "Bearer {$this->adminToken}",
    ])->assertOk();

    expect(collect($response->json('data'))->pluck('id'))->toContain($match->id);
    expect(collect($response->json('data')))->toHaveCount(1);
});

it('refuses a student access to the roster endpoint', function () {
    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;

    $this->getJson('/api/v1/admin/students', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});
