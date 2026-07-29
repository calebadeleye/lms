export interface CertificateSummary {
  id: number;
  certificate_number: string;
  verification_code: string;
  recipient_name: string;
  course_title: string;
  completed_at: string;
  issued_at: string;
  revoked_at: string | null;
  revoked_reason: string | null;
}

export interface CertificateVerification {
  certificate_number: string;
  recipient_name: string;
  course_title: string;
  tenant_name: string;
  completed_at: string;
  issued_at: string;
  valid: boolean;
  revoked_reason: string | null;
}
