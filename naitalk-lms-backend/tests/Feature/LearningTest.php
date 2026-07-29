<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\CourseCategory;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Models\Quiz;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

function makeStudent(\App\Domain\Tenancy\Models\Tenant $tenant): User
{
    app(TenantContext::class)->set($tenant);
    $role = Role::forTenant($tenant->id)->where('slug', 'student')->firstOrFail();
    $user = User::factory()->create();
    TenantUser::create(['user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    return $user;
}

function makeCourseAdmin(\App\Domain\Tenancy\Models\Tenant $tenant): User
{
    app(TenantContext::class)->set($tenant);
    $role = Role::forTenant($tenant->id)->where('slug', 'content-manager')->firstOrFail();
    $user = User::factory()->create();
    TenantUser::create(['user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
    app(TenantContext::class)->clear();

    return $user;
}

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Learn Co');
    $this->hostname = $this->tenant->domains()->first()->hostname;
    $this->url = fn (string $path) => "http://{$this->hostname}{$path}";

    app(TenantContext::class)->set($this->tenant);
    $this->category = CourseCategory::create(['name' => 'HR', 'slug' => 'hr']);
    $this->course = Course::create([
        'category_id' => $this->category->id,
        'title' => 'HR Fundamentals', 'slug' => 'hr-fundamentals',
        'status' => 'published', 'pricing_type' => 'free', 'published_at' => now(),
    ]);
    $this->module = $this->course->modules()->create(['title' => 'Module 1', 'sort_order' => 0]);
    $this->videoLesson = $this->module->lessons()->create([
        'title' => 'Intro video', 'type' => 'video', 'duration_seconds' => 100,
        'is_mandatory' => true, 'sort_order' => 0,
    ]);
    $this->readingLesson = $this->module->lessons()->create([
        'title' => 'Reading', 'type' => 'rich_text', 'content' => ['body' => 'hello'],
        'is_mandatory' => true, 'sort_order' => 1,
    ]);
    app(TenantContext::class)->clear();

    $this->student = makeStudent($this->tenant);
    $this->studentToken = $this->student->createToken('test')->plainTextToken;
});

it('lists published courses publicly with no auth required', function () {
    $this->getJson(($this->url)('/api/v1/courses'))
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'hr-fundamentals');
});

it('does not list draft courses in the public catalogue', function () {
    app(TenantContext::class)->set($this->tenant);
    Course::create(['title' => 'Draft Course', 'slug' => 'draft-course', 'status' => 'draft', 'pricing_type' => 'free']);
    app(TenantContext::class)->clear();

    $response = $this->getJson(($this->url)('/api/v1/courses'))->assertOk();

    expect(collect($response->json('data'))->pluck('slug'))->not->toContain('draft-course');
});

it('hides a category with no published courses from the public catalogue sidebar, but not from admin category management', function () {
    app(TenantContext::class)->set($this->tenant);
    $emptyCategory = CourseCategory::create(['name' => 'Empty Category', 'slug' => 'empty-category']);
    Course::create([
        'category_id' => $emptyCategory->id, 'title' => 'Still A Draft', 'slug' => 'still-a-draft',
        'status' => 'draft', 'pricing_type' => 'free',
    ]);
    app(TenantContext::class)->clear();

    $public = $this->getJson(($this->url)('/api/v1/course-categories?only_with_published=1'))->assertOk();
    expect(collect($public->json('data'))->pluck('name'))->toContain('HR')->not->toContain('Empty Category');

    $admin = $this->getJson(($this->url)('/api/v1/course-categories'))->assertOk();
    expect(collect($admin->json('data'))->pluck('name'))->toContain('HR', 'Empty Category');
});

it('enrols a student in a free course', function () {
    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    $exists = Enrolment::where('course_id', $this->course->id)->where('user_id', $this->student->id)->exists();
    expect($exists)->toBeTrue();
});

it('refuses to enrol in a paid course — checkout is Phase 3', function () {
    app(TenantContext::class)->set($this->tenant);
    $paidCourse = Course::create([
        'title' => 'Paid Course', 'slug' => 'paid-course', 'status' => 'published',
        'pricing_type' => 'paid', 'price_cents' => 500000, 'published_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)("/api/v1/courses/{$paidCourse->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(422);
});

it('clamps a client-submitted video position to the lesson duration and marks complete at 90%', function () {
    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    // Try to fake a position far beyond the lesson's 100-second duration.
    $response = $this->postJson(($this->url)("/api/v1/lessons/{$this->videoLesson->id}/progress/position"), [
        'position_seconds' => 999999,
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertOk();

    expect($response->json('data.video_position_seconds'))->toBe($this->videoLesson->duration_seconds);
    expect($response->json('data.status'))->toBe('completed'); // clamped position still exceeds 90%
});

it('does not mark a video lesson complete below the 90% watched threshold', function () {
    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    $response = $this->postJson(($this->url)("/api/v1/lessons/{$this->videoLesson->id}/progress/position"), [
        'position_seconds' => 50, // 50% of 100s
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertOk();

    expect($response->json('data.status'))->toBe('in_progress');
});

it('computes course completion percentage across mandatory lessons and completes the enrolment at 100%', function () {
    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    $this->postJson(($this->url)("/api/v1/lessons/{$this->videoLesson->id}/progress/position"), [
        'position_seconds' => 100,
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertOk();

    $response = $this->postJson(($this->url)("/api/v1/lessons/{$this->readingLesson->id}/progress/complete"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertOk();

    expect($response->json('data.course_completion_percent'))->toBe(100);

    $enrolment = Enrolment::where('course_id', $this->course->id)->where('user_id', $this->student->id)->firstOrFail();
    expect($enrolment->status)->toBe('completed');
});

it('auto-grades a quiz and marks the quiz lesson complete on a passing attempt', function () {
    app(TenantContext::class)->set($this->tenant);
    $quizLesson = $this->module->lessons()->create(['title' => 'Check', 'type' => 'quiz', 'is_mandatory' => true, 'sort_order' => 2]);
    $quiz = Quiz::create(['lesson_id' => $quizLesson->id, 'passing_score_percent' => 70]);
    $question = $quiz->questions()->create(['type' => 'multiple_choice', 'question_text' => '2+2?', 'points' => 10, 'sort_order' => 0]);
    $correct = $question->options()->create(['option_text' => '4', 'is_correct' => true, 'sort_order' => 0]);
    $question->options()->create(['option_text' => '5', 'is_correct' => false, 'sort_order' => 1]);
    app(TenantContext::class)->clear();

    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    $attempt = $this->postJson(($this->url)("/api/v1/lessons/{$quizLesson->id}/quiz/attempts"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    $result = $this->postJson(($this->url)("/api/v1/quiz-attempts/{$attempt->json('data.id')}/submit"), [
        'answers' => [['question_id' => $question->id, 'selected_option_ids' => [$correct->id]]],
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertOk();

    expect($result->json('data.score_percent'))->toBe(100);
    expect($result->json('data.passed'))->toBeTrue();

    $progress = \App\Domain\Learning\Models\LessonProgress::whereHas(
        'enrolment', fn ($q) => $q->where('user_id', $this->student->id)
    )->where('lesson_id', $quizLesson->id)->first();

    expect($progress->status)->toBe('completed');
});

it('cannot access another tenant\'s course through the public catalogue or by id', function () {
    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Co');
    $otherHostname = $otherTenant->domains()->first()->hostname;

    app(TenantContext::class)->set($otherTenant);
    $otherCourse = Course::create([
        'title' => 'Other Tenant Course', 'slug' => 'other-tenant-course',
        'status' => 'published', 'pricing_type' => 'free', 'published_at' => now(),
    ]);
    app(TenantContext::class)->clear();

    // Tenant A's catalogue must not list tenant B's course.
    $response = $this->getJson(($this->url)('/api/v1/courses'))->assertOk();
    expect(collect($response->json('data'))->pluck('slug'))->not->toContain('other-tenant-course');

    // Tenant A's student, hitting tenant A's own domain, cannot enrol in
    // tenant B's course by id even if they know it exists.
    $this->postJson(($this->url)("/api/v1/courses/{$otherCourse->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(404);

    // And can't view it by slug either — the public show route is scoped
    // to tenant A's own domain regardless of hostname A vs B's data.
    $this->getJson(($this->url)('/api/v1/courses/other-tenant-course'))->assertStatus(404);
});

it('requires the courses.create permission to create a course', function () {
    $this->postJson(($this->url)('/api/v1/admin/courses'), [
        'title' => 'New Course', 'pricing_type' => 'free',
    ], ['Authorization' => "Bearer {$this->studentToken}"])->assertStatus(403);
});

it('lets a content manager create and publish a course', function () {
    $admin = makeCourseAdmin($this->tenant);
    $token = $admin->createToken('test')->plainTextToken;

    $created = $this->postJson(($this->url)('/api/v1/admin/courses'), [
        'title' => 'New Course', 'pricing_type' => 'free',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    expect($created->json('data.status'))->toBe('draft');
    // Eloquent doesn't reflect a DB column default back onto the in-memory
    // model after create() — currency isn't even in validateCourse()'s
    // rules, so this was coming back null and crashing the frontend's price
    // formatter the moment it tried to render a freshly-created course.
    expect($created->json('data.currency'))->toBe('NGN');

    $this->postJson(($this->url)("/api/v1/admin/courses/{$created->json('data.id')}/publish"), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('data.status', 'published');
});

// --- Lesson materials (PDF/PPT/etc uploads) ------------------------------

it('lets a content manager upload a material to a file-type lesson', function () {
    \Illuminate\Support\Facades\Storage::fake('tenants');
    $admin = makeCourseAdmin($this->tenant);
    $token = $admin->createToken('test')->plainTextToken;

    app(TenantContext::class)->set($this->tenant);
    $fileLesson = $this->module->lessons()->create(['title' => 'Handbook', 'type' => 'file', 'sort_order' => 2]);
    app(TenantContext::class)->clear();

    $file = \Illuminate\Http\UploadedFile::fake()->create('handbook.pdf', 500, 'application/pdf');

    $response = $this->postJson(($this->url)("/api/v1/admin/lessons/{$fileLesson->id}/material"), ['file' => $file], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    expect($response->json('data.video_path'))->toBe("/api/v1/lessons/{$fileLesson->id}/material");
    expect($response->json('data.content.material_filename'))->toBe('handbook.pdf');
});

it('lets an enrolled student download a lesson material but blocks a non-enrolled one', function () {
    \Illuminate\Support\Facades\Storage::fake('tenants');
    $admin = makeCourseAdmin($this->tenant);
    $adminToken = $admin->createToken('test')->plainTextToken;

    app(TenantContext::class)->set($this->tenant);
    $fileLesson = $this->module->lessons()->create(['title' => 'Handbook', 'type' => 'file', 'sort_order' => 2]);
    app(TenantContext::class)->clear();

    $file = \Illuminate\Http\UploadedFile::fake()->create('handbook.pdf', 500, 'application/pdf');
    $this->postJson(($this->url)("/api/v1/admin/lessons/{$fileLesson->id}/material"), ['file' => $file], [
        'Authorization' => "Bearer {$adminToken}",
    ])->assertOk();

    // Not enrolled yet — blocked.
    $this->getJson(($this->url)("/api/v1/lessons/{$fileLesson->id}/material"), [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertStatus(403);

    $this->postJson(($this->url)("/api/v1/courses/{$this->course->id}/enrol"), [], [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertCreated();

    // Enrolled now — the actual file streams back.
    $this->get(($this->url)("/api/v1/lessons/{$fileLesson->id}/material"), [
        'Authorization' => "Bearer {$this->studentToken}",
    ])->assertOk();
});
