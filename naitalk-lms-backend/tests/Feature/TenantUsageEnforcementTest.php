<?php

use App\Domain\Billing\Models\PlatformPlan;
use App\Domain\Billing\Models\SubscriptionOverride;
use App\Domain\Identity\Models\Role;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformPlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tenants');
    $this->seed(PermissionSeeder::class);
    $this->seed(PlatformPlanSeeder::class);

    // Free plan has the tightest limits (1 administrator, 1 instructor,
    // 1 coach, 25 active students, 2 published courses, 1GB storage) —
    // easiest to actually hit in a test.
    $freePlan = PlatformPlan::where('code', 'free')->firstOrFail();
    $this->tenant = app(TenantProvisioningService::class)->provision(
        name: 'Usage Co', plan: $freePlan, subscriptionStatus: 'active'
    );
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    $this->owner = makeUserWithRole($this->tenant, 'tenant-owner');
    $this->ownerToken = $this->owner->createToken('t')->plainTextToken;
});

it('blocks inviting a second coach once the Free plan\'s 1-coach limit is reached', function () {
    Notification::fake();

    app(TenantContext::class)->set($this->tenant);
    $coachRole = Role::forTenant($this->tenant->id)->where('slug', 'coach')->firstOrFail();
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'coach1@example.com', 'role_id' => $coachRole->id,
    ], ['Authorization' => "Bearer {$this->ownerToken}"])->assertCreated();

    $blocked = $this->postJson(($this->url)('/api/v1/admin/invitations'), [
        'email' => 'coach2@example.com', 'role_id' => $coachRole->id,
    ], ['Authorization' => "Bearer {$this->ownerToken}"]);

    $blocked->assertStatus(422);
    expect($blocked->json('errors.role_id.0'))->toContain('limit');

    app(TenantContext::class)->set($this->tenant);
    expect(\App\Domain\Identity\Models\Invitation::where('email', 'coach2@example.com')->exists())->toBeFalse();
    app(TenantContext::class)->clear();
});

it('blocks promoting a second person to administrator once the 1-administrator seat is filled', function () {
    // tenant-owner and tenant-administrator both count toward
    // max_administrators (Free plan limit: 1) — the owner created in
    // beforeEach already fills that single seat.
    app(TenantContext::class)->set($this->tenant);
    $adminRole = Role::forTenant($this->tenant->id)->where('slug', 'tenant-administrator')->firstOrFail();
    app(TenantContext::class)->clear();

    $other = makeUserWithRole($this->tenant, 'student');

    $blocked = $this->putJson(($this->url)("/api/v1/admin/tenant-users/{$other->id}/role"), [
        'role_id' => $adminRole->id,
    ], ['Authorization' => "Bearer {$this->ownerToken}"]);

    $blocked->assertStatus(422);
});

it('blocks publishing a third course once the Free plan\'s 2-published-course limit is reached', function () {
    app(TenantContext::class)->set($this->tenant);
    $courses = collect(range(1, 3))->map(fn ($i) => Course::create([
        'title' => "Course {$i}", 'slug' => "course-{$i}", 'status' => 'draft', 'pricing_type' => 'free',
    ]));
    app(TenantContext::class)->clear();

    foreach ($courses->take(2) as $course) {
        $this->postJson(($this->url)("/api/v1/admin/courses/{$course->id}/publish"), [], [
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->assertOk();
    }

    $blocked = $this->postJson(($this->url)("/api/v1/admin/courses/{$courses[2]->id}/publish"), [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ]);
    $blocked->assertStatus(422);

    app(TenantContext::class)->set($this->tenant);
    expect($courses[2]->fresh()->status)->toBe('draft');
    app(TenantContext::class)->clear();
});

it('frees a published-course slot again after unpublishing', function () {
    app(TenantContext::class)->set($this->tenant);
    $courses = collect(range(1, 3))->map(fn ($i) => Course::create([
        'title' => "Course {$i}", 'slug' => "course-{$i}", 'status' => 'draft', 'pricing_type' => 'free',
    ]));
    app(TenantContext::class)->clear();

    foreach ($courses->take(2) as $course) {
        $this->postJson(($this->url)("/api/v1/admin/courses/{$course->id}/publish"), [], [
            'Authorization' => "Bearer {$this->ownerToken}",
        ])->assertOk();
    }

    $this->postJson(($this->url)("/api/v1/admin/courses/{$courses[0]->id}/unpublish"), [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    $this->postJson(($this->url)("/api/v1/admin/courses/{$courses[2]->id}/publish"), [], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();
});

it('blocks a new free enrolment once the active-student limit is reached, but never a returning student', function () {
    app(TenantContext::class)->set($this->tenant);
    SubscriptionOverride::create(['feature_key' => 'max_active_students', 'value' => '1']);
    $course = Course::create([
        'title' => 'Course A', 'slug' => 'course-a', 'status' => 'published', 'pricing_type' => 'free', 'published_at' => now(),
    ]);
    $secondCourse = Course::create([
        'title' => 'Course B', 'slug' => 'course-b', 'status' => 'published', 'pricing_type' => 'free', 'published_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    $student1 = makeUserWithRole($this->tenant, 'student');
    $student1Token = $student1->createToken('t')->plainTextToken;
    $student2 = makeUserWithRole($this->tenant, 'student');
    $student2Token = $student2->createToken('t')->plainTextToken;

    // Fills the 1-active-student limit.
    $this->postJson(($this->url)("/api/v1/courses/{$course->id}/enrol"), [], [
        'Authorization' => "Bearer {$student1Token}",
    ])->assertCreated();

    // A cached Sanctum guard instance persists across requests within the
    // same test (the app container isn't rebooted between simulated
    // requests) — without clearing it, a request bearing student2Token
    // would incorrectly resolve as student1, the previously-authenticated
    // user. See TenantProvisioningFlowTest.php for the same pattern.
    $this->app->make('auth')->forgetGuards();

    // A brand-new student is blocked.
    $this->postJson(($this->url)("/api/v1/courses/{$course->id}/enrol"), [], [
        'Authorization' => "Bearer {$student2Token}",
    ])->assertStatus(422);

    $this->app->make('auth')->forgetGuards();

    // The already-counted student enrolling in a SECOND course must not
    // be blocked — they don't add a new distinct active student.
    $this->postJson(($this->url)("/api/v1/courses/{$secondCourse->id}/enrol"), [], [
        'Authorization' => "Bearer {$student1Token}",
    ])->assertCreated();
});

it('blocks a lesson-material upload once the tenant is over its storage limit', function () {
    app(TenantContext::class)->set($this->tenant);
    // Force the limit down to effectively zero rather than uploading a
    // real 1GB file in a test.
    SubscriptionOverride::create(['feature_key' => 'storage_gb', 'value' => '0.0000001']);
    $course = Course::create(['title' => 'Course', 'slug' => 'course', 'status' => 'draft', 'pricing_type' => 'free']);
    $module = $course->modules()->create(['title' => 'Module 1', 'sort_order' => 0]);
    $lesson = $module->lessons()->create(['title' => 'Lesson', 'type' => 'rich_text', 'is_mandatory' => true, 'sort_order' => 0]);
    app(TenantContext::class)->clear();

    $file = UploadedFile::fake()->create('handout.pdf', 50, 'application/pdf');

    $response = $this->postJson(($this->url)("/api/v1/admin/lessons/{$lesson->id}/material"), ['file' => $file], [
        'Authorization' => "Bearer {$this->ownerToken}",
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.file.0'))->toContain('storage limit');
});

it('exposes the tenant\'s own plan and live usage via GET /tenant/subscription', function () {
    app(TenantContext::class)->set($this->tenant);
    $coachRole = Role::forTenant($this->tenant->id)->where('slug', 'coach')->firstOrFail();
    app(TenantContext::class)->clear();
    makeUserWithRole($this->tenant, 'coach');

    $response = $this->getJson(($this->url)('/api/v1/tenant/subscription'), [
        'Authorization' => "Bearer {$this->ownerToken}",
    ])->assertOk();

    expect($response->json('data.plan.code'))->toBe('free');
    expect($response->json('data.subscription.status'))->toBe('active');

    $usage = collect($response->json('data.usage'))->keyBy('key');
    expect($usage['max_administrators']['used'])->toBe(1);
    expect($usage['max_administrators']['limit'])->toBe(1);
    expect($usage['max_coaches']['used'])->toBe(1);
    expect($usage['max_coaches']['limit'])->toBe(1);
    expect($usage['max_coaches']['at_limit'])->toBeTrue();
});
