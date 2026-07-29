<?php

namespace App\Domain\Learning\Http\Controllers;

use App\Domain\Learning\Models\Certificate;
use App\Domain\Learning\Models\Enrolment;
use App\Domain\Learning\Services\CertificateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    /**
     * Public — no `tenant` or `auth` middleware. Whoever is checking a
     * certificate's authenticity (an employer scanning a printed QR code)
     * has no tenant hostname context, so the tenant is resolved from the
     * globally-unique verification code instead. A revoked certificate
     * still resolves (valid: false) rather than 404ing, so "revoked" stays
     * distinguishable from "never existed."
     */
    public function verify(string $code)
    {
        $certificate = Certificate::withoutTenancy(fn () => Certificate::where('verification_code', $code)
            ->with('tenant:id,name')
            ->first()
        );

        if (! $certificate) {
            return response()->json(['data' => null], 404);
        }

        return response()->json(['data' => [
            'certificate_number' => $certificate->certificate_number,
            'recipient_name' => $certificate->recipient_name,
            'course_title' => $certificate->course_title,
            'tenant_name' => $certificate->tenant->name,
            'completed_at' => $certificate->completed_at,
            'issued_at' => $certificate->issued_at,
            'valid' => $certificate->isValid(),
            'revoked_reason' => $certificate->revoked_reason,
        ]]);
    }

    public function mine(Request $request)
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => $certificates]);
    }

    /** Admin — every certificate issued in the tenant (certificates.issue). */
    public function adminIndex()
    {
        $certificates = Certificate::with('user:id,name,email')
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => $certificates]);
    }

    /**
     * Manual issue for an enrolment that completed before the course had
     * certificates enabled — CertificateService::issueForEnrolment() is
     * idempotent, so calling this on an enrolment that already has one just
     * returns the existing certificate rather than duplicating it.
     */
    public function issueManually(string $enrolmentId)
    {
        $enrolment = Enrolment::where('status', 'completed')->findOrFail($enrolmentId);

        $certificate = $this->certificates->issueForEnrolment($enrolment);

        if (! $certificate) {
            throw ValidationException::withMessages([
                'course' => ['This course does not have certificates enabled.'],
            ]);
        }

        return response()->json(['data' => $certificate], 201);
    }

    public function revoke(Request $request, string $certificateId)
    {
        $certificate = Certificate::findOrFail($certificateId);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $certificate = $this->certificates->revoke($certificate, $data['reason']);

        return response()->json(['data' => $certificate]);
    }
}
