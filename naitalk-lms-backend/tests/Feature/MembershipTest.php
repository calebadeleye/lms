<?php

use App\Domain\Learning\Models\Course;
use App\Domain\Membership\Models\LearnerMembershipPlan;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('gates a membership-only course: enrolment fails without membership, succeeds once an active subscription exists', function () {
    $plan = LearnerMembershipPlan::create([
        'name' => 'Community', 'slug' => 'community', 'billing_period' => 'monthly',
        'price_cents' => 500000, 'currency' => 'NGN', 'is_active' => true,
    ]);
    $course = Course::create([
        'title' => 'Members Only Course', 'slug' => 'members-only-course', 'status' => 'published',
        'pricing_type' => 'membership_only', 'published_at' => now(),
    ]);

    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;

    $this->postJson("/api/v1/courses/{$course->id}/enrol", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(422);

    // Every plan is paid now — activation only ever happens as the result of
    // a completed checkout (OrderFulfillmentService), never a direct
    // subscribe endpoint, so it's exercised here via the service directly.
    app(\App\Domain\Membership\Services\MembershipService::class)->activate($student, $plan);

    $response = $this->postJson("/api/v1/courses/{$course->id}/enrol", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertCreated();

    expect($response->json('data.source'))->toBe('membership');
});

it('revokes lesson access the moment a membership lapses, even after enrolment', function () {
    $plan = LearnerMembershipPlan::create([
        'name' => 'Community', 'slug' => 'community', 'billing_period' => 'monthly',
        'price_cents' => 500000, 'currency' => 'NGN', 'is_active' => true,
    ]);
    $course = Course::create([
        'title' => 'Members Only Course', 'slug' => 'members-only-course', 'status' => 'published',
        'pricing_type' => 'membership_only', 'published_at' => now(),
    ]);
    $module = $course->modules()->create(['title' => 'Module 1', 'sort_order' => 1]);
    $lesson = $module->lessons()->create([
        'title' => 'Lesson 1', 'type' => 'text', 'content' => ['body' => 'Hi'],
        'is_preview' => false, 'is_mandatory' => true, 'available_after_days' => 0, 'sort_order' => 1,
    ]);

    $student = makeUserWithRole('student');
    $token = $student->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    // Activate membership via the service directly, the same way a
    // completed checkout would (see OrderFulfillmentService), then enrol.
    $subscription = app(\App\Domain\Membership\Services\MembershipService::class)->activate($student, $plan);

    $this->postJson("/api/v1/courses/{$course->id}/enrol", [], $headers)->assertCreated();

    $this->getJson("/api/v1/lessons/{$lesson->id}", $headers)->assertOk();

    // Membership lapses.
    $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    $response = $this->getJson("/api/v1/lessons/{$lesson->id}", $headers);
    $response->assertStatus(403);
    expect($response->json('errors.0.code'))->toBe('membership_required');
});

it('lets an administrator create and update membership plans', function () {
    $admin = makeUserWithRole('administrator');
    $adminToken = $admin->createToken('t')->plainTextToken;

    $create = $this->postJson('/api/v1/admin/membership-plans', [
        'name' => 'Elite', 'billing_period' => 'annual', 'price_cents' => 4_800_00, 'currency' => 'NGN',
    ], ['Authorization' => "Bearer {$adminToken}"])->assertCreated();

    $planId = $create->json('data.id');

    $this->putJson("/api/v1/admin/membership-plans/{$planId}", [
        'name' => 'Elite Renamed',
    ], ['Authorization' => "Bearer {$adminToken}"])->assertOk()
        ->assertJsonPath('data.name', 'Elite Renamed');
});

it('refuses to let a student update a membership plan', function () {
    $plan = LearnerMembershipPlan::create([
        'name' => 'Elite', 'slug' => 'elite', 'billing_period' => 'annual',
        'price_cents' => 4_800_00, 'currency' => 'NGN', 'is_active' => true,
    ]);

    $student = makeUserWithRole('student');
    $studentToken = $student->createToken('t')->plainTextToken;

    $this->putJson("/api/v1/admin/membership-plans/{$plan->id}", [
        'name' => 'Elite Renamed',
    ], ['Authorization' => "Bearer {$studentToken}"])->assertStatus(403);
});
