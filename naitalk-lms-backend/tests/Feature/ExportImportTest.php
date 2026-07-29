<?php

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\EnrolmentService;
use App\Domain\Learning\Services\ProgressService;
use App\Domain\Tenancy\Models\ExportJob;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantImportService;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tenants');
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Export Co');
});

function seedExportableContent(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);
    $course = Course::create([
        'title' => 'HR Fundamentals', 'slug' => 'hr-fundamentals', 'status' => 'published',
        'pricing_type' => 'free', 'certificate_enabled' => true, 'published_at' => now(),
    ]);
    $module = $course->modules()->create(['title' => 'Module 1', 'sort_order' => 0]);
    $lesson = $module->lessons()->create([
        'title' => 'Reading', 'type' => 'rich_text', 'content' => ['body' => 'hello'],
        'is_mandatory' => true, 'sort_order' => 0,
    ]);
    app(TenantContext::class)->clear();

    $student = makeUserWithRole($tenant, 'student');

    app(TenantContext::class)->set($tenant);
    $enrolment = app(EnrolmentService::class)->enroll($student, $course, 'free');
    app(ProgressService::class)->markComplete($enrolment, $lesson);
    app(TenantContext::class)->clear();

    return compact('course', 'student', 'enrolment');
}

it('lets a tenant-administrator request and download their own export', function () {
    seedExportableContent($this->tenant);

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $token = $admin->createToken('t')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $create = $this->postJson(tenantUrl($this->tenant, '/api/v1/admin/exports'), [], $headers)->assertCreated();
    $exportJobId = $create->json('data.id');

    // QUEUE_CONNECTION=sync in phpunit.xml — the job already ran.
    $index = $this->getJson(tenantUrl($this->tenant, '/api/v1/admin/exports'), $headers)->assertOk();
    expect($index->json('data.0.status'))->toBe('completed');

    $download = $this->get(tenantUrl($this->tenant, "/api/v1/admin/exports/{$exportJobId}/download"), $headers)->assertOk();
    $payload = json_decode($download->streamedContent(), true);

    expect($payload['tenant']['name'])->toBe('Export Co');
    expect(collect($payload['courses'])->pluck('title'))->toContain('HR Fundamentals');
    expect($payload['certificates'])->toHaveCount(1);
});

it('refuses to let a student request an export', function () {
    $student = makeUserWithRole($this->tenant, 'student');
    $token = $student->createToken('t')->plainTextToken;

    $this->postJson(tenantUrl($this->tenant, '/api/v1/admin/exports'), [], [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(403);
});

it('does not let a tenant download another tenant\'s export', function () {
    seedExportableContent($this->tenant);
    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Export Co');

    app(TenantContext::class)->set($otherTenant);
    $otherExport = ExportJob::create(['status' => 'completed', 'file_path' => 'irrelevant.json']);
    app(TenantContext::class)->clear();

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $token = $admin->createToken('t')->plainTextToken;

    $this->get(tenantUrl($this->tenant, "/api/v1/admin/exports/{$otherExport->id}/download"), [
        'Authorization' => "Bearer {$token}",
    ])->assertStatus(404);
});

it('lets platform staff trigger and download any tenant\'s export', function () {
    seedExportableContent($this->tenant);
    $token = makePlatformStaffToken();

    $create = $this->postJson("/api/v1/platform/tenants/{$this->tenant->id}/exports", [], [
        'Authorization' => "Bearer {$token}",
    ])->assertCreated();

    $download = $this->get(
        "/api/v1/platform/tenants/{$this->tenant->id}/exports/{$create->json('data.id')}/download",
        ['Authorization' => "Bearer {$token}"]
    )->assertOk();

    $payload = json_decode($download->streamedContent(), true);
    expect($payload['tenant']['name'])->toBe('Export Co');
});

it('imports an export into a brand-new tenant, restoring courses, enrolments, and certificates', function () {
    ['course' => $course, 'student' => $student] = seedExportableContent($this->tenant);

    app(TenantContext::class)->set($this->tenant);
    $payload = app(App\Domain\Tenancy\Services\TenantExportService::class)->export($this->tenant);
    app(TenantContext::class)->clear();

    $newTenant = app(TenantImportService::class)->importIntoNewTenant($payload, 'Export Co Restored');

    expect($newTenant->id)->not->toBe($this->tenant->id);
    expect($newTenant->name)->toBe('Export Co Restored');

    app(TenantContext::class)->set($newTenant);

    $restoredCourse = Course::where('slug', 'hr-fundamentals')->first();
    expect($restoredCourse)->not->toBeNull();
    expect($restoredCourse->id)->not->toBe($course->id);
    expect($restoredCourse->certificate_enabled)->toBeTrue();

    $restoredEnrolment = Enrolment::where('course_id', $restoredCourse->id)->first();
    expect($restoredEnrolment)->not->toBeNull();
    expect($restoredEnrolment->status)->toBe('completed');

    $restoredCertificate = Certificate::where('course_id', $restoredCourse->id)->first();
    expect($restoredCertificate)->not->toBeNull();
    expect($restoredCertificate->recipient_name)->toBe($student->name);
    // A fresh sequence for the new tenant, not a copy of the old one's.
    expect($restoredCertificate->certificate_number)->toStartWith(strtoupper($newTenant->slug));

    $restoredStudent = \App\Models\User::where('email', $student->email)->first();
    expect($restoredStudent->id)->toBe($student->id); // same global user, reused via email match

    app(TenantContext::class)->clear();

    // The original tenant's data is completely untouched.
    app(TenantContext::class)->set($this->tenant);
    expect(Course::count())->toBe(1);
    app(TenantContext::class)->clear();
});

it('gives the imported tenant its own default domain rather than colliding with the source tenant\'s', function () {
    seedExportableContent($this->tenant);

    app(TenantContext::class)->set($this->tenant);
    $payload = app(App\Domain\Tenancy\Services\TenantExportService::class)->export($this->tenant);
    app(TenantContext::class)->clear();

    $newTenant = app(TenantImportService::class)->importIntoNewTenant($payload, 'Export Co Restored');

    expect($newTenant->domains()->first()->hostname)->not->toBe($this->tenant->domains()->first()->hostname);
});

// --- The platform HTTP route itself (the tests above only exercise
// TenantImportService directly) — this is what the admin-facing restore
// form in the platform UI actually calls.

it('lets platform staff restore a tenant via the import HTTP route', function () {
    seedExportableContent($this->tenant);

    app(TenantContext::class)->set($this->tenant);
    $payload = app(App\Domain\Tenancy\Services\TenantExportService::class)->export($this->tenant);
    app(TenantContext::class)->clear();

    $token = makePlatformStaffToken();

    $response = $this->postJson('/api/v1/platform/tenants/import', [
        'new_tenant_name' => 'Export Co Restored via HTTP',
        'payload' => $payload,
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    expect($response->json('data.name'))->toBe('Export Co Restored via HTTP');
    expect($response->json('data.id'))->not->toBe($this->tenant->id);
    expect(Tenant::where('name', 'Export Co Restored via HTTP')->exists())->toBeTrue();
});

it('refuses the import route without exports.manage', function () {
    $token = makePlatformStaffToken('platform-finance');

    $this->postJson('/api/v1/platform/tenants/import', [
        'new_tenant_name' => 'Should Fail',
        'payload' => ['tenant' => ['name' => 'x']],
    ], ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});
