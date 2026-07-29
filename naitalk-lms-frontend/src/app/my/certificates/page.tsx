import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import type { CertificateSummary } from '@/lib/certificate-types';

export default async function MyCertificatesPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  const certificates = await apiFetch<{ data: CertificateSummary[] }>('/api/v1/my/certificates');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={studentNav}
      userName={me.user.name}
      activeHref="/my/certificates"
    >
      <h1 className="text-xl font-bold text-neutral-900">My Certificates</h1>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {certificates.data.map((certificate) => (
          <Link
            key={certificate.id}
            href={`/certificates/${certificate.verification_code}`}
            target="_blank"
            className={`rounded-xl border bg-white p-5 hover:shadow-sm ${
              certificate.revoked_at ? 'border-neutral-200 opacity-60' : 'border-[var(--tenant-accent)]/40'
            }`}
          >
            <p className="text-3xl">🎓</p>
            <h3 className="mt-2 font-semibold text-neutral-900">{certificate.course_title}</h3>
            <p className="mt-1 text-xs text-neutral-500">
              Completed {new Date(certificate.completed_at).toLocaleDateString()}
            </p>
            <p className="mt-1 font-mono text-xs text-neutral-400">{certificate.certificate_number}</p>
            {certificate.revoked_at && <p className="mt-2 text-xs font-semibold text-red-600">Revoked</p>}
          </Link>
        ))}
        {certificates.data.length === 0 && (
          <p className="text-sm text-neutral-500">
            No certificates yet — complete a certificate-enabled course to earn one.
          </p>
        )}
      </div>
    </DashboardShell>
  );
}
