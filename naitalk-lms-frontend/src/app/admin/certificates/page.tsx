import Link from 'next/link';
import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { CertificateRevokeButton } from '@/components/certificate-revoke-button';
import type { CertificateSummary } from '@/lib/certificate-types';

interface AdminCertificate extends CertificateSummary {
  user: { id: number; name: string; email: string };
}

export default async function AdminCertificatesPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('certificates.issue')) redirect('/dashboard');

  const certificates = await apiFetch<{ data: AdminCertificate[] }>('/api/v1/admin/certificates');
  const canRevoke = me.permissions.includes('certificates.revoke');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={adminNav}
      userName={me.user.name}
      activeHref="/admin/certificates"
    >
      <h1 className="text-xl font-bold text-neutral-900">Certificates</h1>
      <p className="mt-1 text-sm text-neutral-500">Every certificate issued in this academy.</p>

      <div className="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        {certificates.data.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">No certificates have been issued yet.</p>
        ) : (
          <ul className="divide-y divide-neutral-100">
            {certificates.data.map((certificate) => (
              <li key={certificate.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                  <p className="text-sm font-medium text-neutral-900">
                    {certificate.recipient_name} &middot; {certificate.course_title}
                  </p>
                  <p className="mt-0.5 text-xs text-neutral-500">
                    {certificate.user.email} &middot; {certificate.certificate_number} &middot; Issued{' '}
                    {new Date(certificate.issued_at).toLocaleDateString()}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  {certificate.revoked_at ? (
                    <span className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600">
                      Revoked
                    </span>
                  ) : (
                    <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                      Valid
                    </span>
                  )}
                  <Link
                    href={`/certificates/${certificate.verification_code}`}
                    target="_blank"
                    className="text-xs font-semibold text-[var(--brand-primary)] hover:underline"
                  >
                    View
                  </Link>
                  {canRevoke && !certificate.revoked_at && (
                    <CertificateRevokeButton certificateId={certificate.id} />
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </DashboardShell>
  );
}
