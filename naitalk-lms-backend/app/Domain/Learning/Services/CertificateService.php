<?php

namespace App\Domain\Learning\Services;

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Enrolment;
use Illuminate\Support\Str;

/**
 * The only place a certificate is created or revoked. Called by
 * CourseCompletionService right after an enrolment flips to `completed` —
 * never exposed as a student-facing "claim my certificate" action, since
 * eligibility is entirely server-derived from that same completion event.
 */
class CertificateService
{
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

        return Certificate::create([
            'user_id' => $enrolment->user_id,
            'course_id' => $course->id,
            'enrolment_id' => $enrolment->id,
            'certificate_number' => $this->nextCertificateNumber(),
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
     * a collision needs two completions landing in the same instant, which
     * `unique(certificate_number)` would reject outright. Accepted at this
     * scale; a real sequence table is the production hardening if that ever
     * becomes a measured problem.
     */
    private function nextCertificateNumber(): string
    {
        $year = now()->year;
        $countThisYear = Certificate::whereYear('issued_at', $year)->count();
        $prefix = Str::upper(Str::slug(config('app.name'), ''));

        return sprintf('%s-%d-%04d', $prefix, $year, $countThisYear + 1);
    }
}
