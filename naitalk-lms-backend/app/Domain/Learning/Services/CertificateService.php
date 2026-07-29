<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Str;

/**
 * The only place a certificate is created or revoked. Called by
 * CourseCompletionService right after an enrolment flips to `completed` —
 * never exposed as a student-facing "claim my certificate" action, since
 * eligibility is entirely server-derived from that same completion event.
 */
class CertificateService
{
    public function __construct(private TenantContext $tenantContext) {}

    public function issueForEnrolment(Enrolment $enrolment): ?Certificate
    {
        $course = $enrolment->course;

        if (! $course->certificate_enabled) {
            return null;
        }

        // Idempotent: CourseCompletionService::recompute() can run more than
        // once for the same enrolment (e.g. a mandatory lesson's progress
        // being recalculated) — never issue a second certificate for it.
        $existing = Certificate::where('enrolment_id', $enrolment->id)->first();
        if ($existing) {
            return $existing;
        }

        $enrolment->loadMissing('user');
        $tenant = $this->tenantContext->tenant();

        return Certificate::create([
            'user_id' => $enrolment->user_id,
            'course_id' => $course->id,
            'enrolment_id' => $enrolment->id,
            'certificate_number' => $this->nextCertificateNumber($tenant->slug),
            'verification_code' => (string) Str::uuid(),
            'recipient_name' => $enrolment->user->name,
            'course_title' => $course->title,
            'completed_at' => $enrolment->completed_at,
            'issued_at' => now(),
        ]);
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        $certificate->update(['revoked_at' => now(), 'revoked_reason' => $reason]);

        return $certificate->fresh();
    }

    /**
     * Count-then-format rather than a dedicated sequence table — simple, and
     * a collision needs two completions in the same tenant/year landing in
     * the same instant, which `unique(tenant_id, certificate_number)` would
     * reject outright. Accepted at this scale; a real sequence table is the
     * production hardening if that ever becomes a measured problem.
     */
    private function nextCertificateNumber(string $tenantSlug): string
    {
        $year = now()->year;
        $countThisYear = Certificate::whereYear('issued_at', $year)->count();

        return sprintf('%s-%d-%04d', strtoupper($tenantSlug), $year, $countThisYear + 1);
    }
}
