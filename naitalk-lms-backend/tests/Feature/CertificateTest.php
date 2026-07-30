<?php

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Course;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\EnrolmentService;
use App\Domain\Learning\Services\ProgressService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

// Drives completion via the domain services directly rather than HTTP —
// tests that need a second authenticated actor afterward (an admin
// revoking a certificate, etc.) would otherwise trip the Sanctum
// RequestGuard's cached-resolved-user gotcha from switching bearer tokens
// mid-test.
function completeACourse(bool $certificateEnabled): array
{
    $course = Course::create([
        'title' => 'HR Fundamentals', 'slug' => 'hr-fundamentals', 'status' => 'published',
        'pricing_type' => 'free', 'certificate_enabled' => $certificateEnabled, 'published_at' => now(),
    ]);
    $module = $course->modules()->create(['title' => 'Module 1', 'sort_order' => 0]);
    $lesson = $module->lessons()->create([
        'title' => 'Reading', 'type' => 'rich_text', 'content' => ['body' => 'hello'],
        'is_mandatory' => true, 'sort_order' => 0,
    ]);

    $student = makeUserWithRole('student');

    $enrolment = app(EnrolmentService::class)->enroll($student, $course, 'free');
    app(ProgressService::class)->markComplete($enrolment, $lesson);

    return compact('course', 'student');
}

it('auto-issues a certificate when a certificate-enabled course is completed', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: true);

    $certificate = Certificate::where('user_id', $student->id)->where('course_id', $course->id)->first();

    expect($certificate)->not->toBeNull();
    expect($certificate->recipient_name)->toBe($student->name);
    expect($certificate->course_title)->toBe('HR Fundamentals');
    expect($certificate->isValid())->toBeTrue();
});

it('does not issue a certificate for a course that has certificates disabled', function () {
    ['student' => $student] = completeACourse(certificateEnabled: false);

    expect(Certificate::where('user_id', $student->id)->exists())->toBeFalse();
});

it('lets an administrator manually issue a certificate for a completed enrolment', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: true);

    $enrolment = Enrolment::where('course_id', $course->id)->where('user_id', $student->id)->firstOrFail();

    $admin = makeUserWithRole('administrator');
    $token = $admin->createToken('t')->plainTextToken;

    // Already auto-issued on completion — issueForEnrolment() is idempotent,
    // so a manual call on top just returns the same certificate rather than
    // erroring or duplicating.
    $response = $this->postJson(
        "/api/v1/admin/enrolments/{$enrolment->id}/issue-certificate",
        [],
        ['Authorization' => "Bearer {$token}"]
    )->assertCreated();

    expect(Certificate::where('user_id', $student->id)->where('course_id', $course->id)->count())->toBe(1);
    expect($response->json('data.recipient_name'))->toBe($student->name);
});

it('refuses manual issuance for a course with certificates disabled', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: false);

    $enrolment = Enrolment::where('course_id', $course->id)->where('user_id', $student->id)->firstOrFail();

    $admin = makeUserWithRole('administrator');
    $token = $admin->createToken('t')->plainTextToken;

    $this->postJson(
        "/api/v1/admin/enrolments/{$enrolment->id}/issue-certificate",
        [],
        ['Authorization' => "Bearer {$token}"]
    )->assertStatus(422);
});

it('reports has_certificate on the course roster once one has been issued', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: true);

    $admin = makeUserWithRole('administrator');
    $token = $admin->createToken('t')->plainTextToken;

    $response = $this->getJson(
        "/api/v1/admin/courses/{$course->id}/enrolments",
        ['Authorization' => "Bearer {$token}"]
    )->assertOk();

    expect($response->json('data.0.has_certificate'))->toBeTrue();
});

it('never issues a second certificate for the same enrolment', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: true);

    $enrolment = Enrolment::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();

    // Recompute again — this must stay idempotent even though the enrolment
    // is already completed (CourseCompletionService only re-issues via
    // CertificateService's own firstOrCreate-style guard, not by skipping
    // recompute() itself).
    app(\App\Domain\Learning\Services\CourseCompletionService::class)->recompute($enrolment);

    expect(Certificate::where('enrolment_id', $enrolment->id)->count())->toBe(1);
});

it('verifies a valid certificate publicly by its verification code', function () {
    ['course' => $course, 'student' => $student] = completeACourse(certificateEnabled: true);

    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();

    $response = $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")->assertOk();

    expect($response->json('data.valid'))->toBeTrue();
    expect($response->json('data.recipient_name'))->toBe($student->name);
    expect($response->json('data.issuer_name'))->toBe(config('app.name'));
});

it('returns 404 for an unknown verification code', function () {
    $this->getJson('/api/v1/certificates/verify/'.\Illuminate\Support\Str::uuid())->assertStatus(404);
});

it('lets an admin revoke a certificate, which then verifies as invalid', function () {
    ['course' => $course] = completeACourse(certificateEnabled: true);

    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();

    $admin = makeUserWithRole('administrator');
    $adminToken = $admin->createToken('t')->plainTextToken;

    $this->postJson("/api/v1/admin/certificates/{$certificate->id}/revoke", [
        'reason' => 'Issued in error',
    ], ['Authorization' => "Bearer {$adminToken}"])->assertOk();

    $response = $this->getJson("/api/v1/certificates/verify/{$certificate->verification_code}")->assertOk();
    expect($response->json('data.valid'))->toBeFalse();
    expect($response->json('data.revoked_reason'))->toBe('Issued in error');
});

it('refuses to let a student revoke a certificate', function () {
    ['course' => $course] = completeACourse(certificateEnabled: true);

    $certificate = Certificate::where('course_id', $course->id)->firstOrFail();

    $otherStudent = makeUserWithRole('student');
    $token = $otherStudent->createToken('t')->plainTextToken;

    $this->postJson("/api/v1/admin/certificates/{$certificate->id}/revoke", [
        'reason' => 'nope',
    ], ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});
