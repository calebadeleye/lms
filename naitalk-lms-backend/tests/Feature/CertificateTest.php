<?php

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\EnrolmentService;
use App\Domain\Learning\Services\ProgressService;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantProvisioningService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = app(TenantProvisioningService::class)->provision(name: 'Certificate Co');
});

// Drives completion via the domain services directly rather than HTTP —
// tests that need a second authenticated actor afterward (an admin
// revoking, a different tenant's student) would otherwise trip the Sanctum
// RequestGuard's cached-resolved-user gotcha from switching bearer tokens
// mid-test (see Phase 3's CommerceTest/MembershipTest notes).
function completeACourse(\App\Domain\Tenancy\Models\Tenant $tenant, bool $certificateEnabled): array
{
    app(TenantContext::class)->set($tenant);
    $course = Course::create([
        'title' => 'HR Fundamentals', 'slug' => 'hr-fundamentals', 'status' => 'published',
        'pricing_type' => 'free', 'certificate_enabled' => $certificateEnabled, 'published_at' => now(),
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

    return compact('course', 'student');
}

it('auto-issues a certificate when a certificate-enabled course is completed', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $certificate = Certificate::where('user_id', $student->id)->where('course_id', $course->id)->first();
    app(TenantContext::class)->clear();

    expect($certificate)->not->toBeNull();
    expect($certificate->recipient_name)->toBe($student->name);
    expect($certificate->course_title)->toBe('HR Fundamentals');
    expect($certificate->isValid())->toBeTrue();
});

it('does not issue a certificate for a course that has certificates disabled', function () {
    ['student' => $student] = completeACourse($this->tenant, certificateEnabled: false);

    app(TenantContext::class)->set($this->tenant);
    expect(Certificate::where('user_id', $student->id)->exists())->toBeFalse();
    app(TenantContext::class)->clear();
});

it('lets a tenant-administrator manually issue a certificate for a completed enrolment', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $enrolment = Enrolment::where('course_id', $course->id)->where('user_id', $student->id)->firstOrFail();
    app(TenantContext::class)->clear();

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $token = $admin->createToken('t')->plainTextToken;

    // Already auto-issued on completion — issueForEnrolment() is idempotent,
    // so a manual call on top just returns the same certificate rather than
    // erroring or duplicating.
    $response = $this->postJson(
        tenantUrl($this->tenant, "/api/v1/admin/enrolments/{$enrolment->id}/issue-certificate"),
        [],
        ['Authorization' => "Bearer {$token}"]
    )->assertCreated();

    app(TenantContext::class)->set($this->tenant);
    expect(Certificate::where('user_id', $student->id)->where('course_id', $course->id)->count())->toBe(1);
    app(TenantContext::class)->clear();
    expect($response->json('data.recipient_name'))->toBe($student->name);
});

it('refuses manual issuance for a course with certificates disabled', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: false);

    app(TenantContext::class)->set($this->tenant);
    $enrolment = Enrolment::where('course_id', $course->id)->where('user_id', $student->id)->firstOrFail();
    app(TenantContext::class)->clear();

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $token = $admin->createToken('t')->plainTextToken;

    $this->postJson(
        tenantUrl($this->tenant, "/api/v1/admin/enrolments/{$enrolment->id}/issue-certificate"),
        [],
        ['Authorization' => "Bearer {$token}"]
    )->assertStatus(422);
});

it('reports has_certificate on the course roster once one has been issued', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: true);

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $token = $admin->createToken('t')->plainTextToken;

    $response = $this->getJson(
        tenantUrl($this->tenant, "/api/v1/admin/courses/{$course->id}/enrolments"),
        ['Authorization' => "Bearer {$token}"]
    )->assertOk();

    expect($response->json('data.0.has_certificate'))->toBeTrue();
});

it('never issues a second certificate for the same enrolment', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $enrolment = Enrolment::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();

    // Recompute again — this must stay idempotent even though the enrolment
    // is already completed (CourseCompletionService only re-issues via
    // CertificateService's own firstOrCreate-style guard, not by skipping
    // recompute() itself).
    app(\App\Domain\Learning\Services\CourseCompletionService::class)->recompute($enrolment);

    expect(Certificate::where('enrolment_id', $enrolment->id)->count())->toBe(1);
    app(TenantContext::class)->clear();
});

it('verifies a valid certificate publicly by its verification code', function () {
    ['course' => $course, 'student' => $student] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();
    app(TenantContext::class)->clear();

    $response = $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")->assertOk();

    expect($response->json('data.valid'))->toBeTrue();
    expect($response->json('data.recipient_name'))->toBe($student->name);
    expect($response->json('data.tenant_name'))->toBe('Certificate Co');
});

it('returns 404 for an unknown verification code', function () {
    $this->getJson('/api/v1/certificates/verify/'.\Illuminate\Support\Str::uuid())->assertStatus(404);
});

it('lets an admin revoke a certificate, which then verifies as invalid', function () {
    ['course' => $course] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();
    app(TenantContext::class)->clear();

    $admin = makeUserWithRole($this->tenant, 'tenant-administrator');
    $adminToken = $admin->createToken('t')->plainTextToken;

    $this->postJson(tenantUrl($this->tenant, "/api/v1/admin/certificates/{$certificate->id}/revoke"), [
        'reason' => 'Issued in error',
    ], ['Authorization' => "Bearer {$adminToken}"])->assertOk();

    $response = $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")->assertOk();
    expect($response->json('data.valid'))->toBeFalse();
    expect($response->json('data.revoked_reason'))->toBe('Issued in error');
});

it('refuses to let a student revoke a certificate', function () {
    ['course' => $course] = completeACourse($this->tenant, certificateEnabled: true);

    app(TenantContext::class)->set($this->tenant);
    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();
    app(TenantContext::class)->clear();

    $otherStudent = makeUserWithRole($this->tenant, 'student');
    $token = $otherStudent->createToken('t')->plainTextToken;

    $this->postJson(tenantUrl($this->tenant, "/api/v1/admin/certificates/{$certificate->id}/revoke"), [
        'reason' => 'nope',
    ], ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});

it('scopes certificates to their own tenant', function () {
    completeACourse($this->tenant, certificateEnabled: true);

    $otherTenant = app(TenantProvisioningService::class)->provision(name: 'Other Certificate Co');
    ['student' => $otherStudent] = completeACourse($otherTenant, certificateEnabled: true);
    $otherToken = $otherStudent->createToken('t')->plainTextToken;

    $response = $this->getJson(tenantUrl($otherTenant, '/api/v1/my/certificates'), [
        'Authorization' => "Bearer {$otherToken}",
    ])->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.recipient_name'))->toBe($otherStudent->name);
});
