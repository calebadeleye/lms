import QRCode from 'qrcode';
import { notFound } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { ShareButtons } from '@/components/share-buttons';
import type { CertificateVerification } from '@/lib/certificate-types';

/**
 * Public — reachable with no login and no tenant-domain assumption baked
 * in beyond whatever hostname the visitor is actually on (an employer
 * scanning a printed QR code has no reason to be on the issuing academy's
 * own domain). skipAuth so a visitor's unrelated session cookie, if any,
 * never gets sent along. The QR encodes this exact page's own URL — for
 * scanning a printed/downloaded certificate, not for the on-screen view,
 * which already IS the verification.
 */
export default async function CertificateVerifyPage({
  params,
}: {
  params: Promise<{ code: string }>;
}) {
  const config = BRANDING;

  const user = await getOptionalUser();
  const { code } = await params;

  let certificate: CertificateVerification;
  try {
    const body = await apiFetch<{ data: CertificateVerification }>(`/api/v1/certificates/verify/${code}`, {
      skipAuth: true,
    });
    certificate = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const verifyUrl = `https://${config.domain.primary_hostname}/certificates/${code}`;
  const qrSvg = await QRCode.toString(verifyUrl, { type: 'svg', margin: 1, width: 140 });

  return (
    <div className="flex min-h-full flex-col">
      <div className="print:hidden">
        <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />
      </div>

      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:px-6">
        <div
          className={`rounded-2xl border-4 p-10 text-center print:border-0 ${
            certificate.valid ? 'border-[var(--brand-accent)]' : 'border-neutral-300 opacity-75'
          }`}
        >
          <p className="text-xs font-semibold uppercase tracking-widest text-[var(--brand-primary)]">
            {certificate.tenant_name}
          </p>
          <h1 className="mt-4 text-lg text-neutral-500">Certificate of Completion</h1>
          <p className="mt-6 text-3xl font-bold text-neutral-900">{certificate.recipient_name}</p>
          <p className="mt-4 text-sm text-neutral-600">has successfully completed</p>
          <p className="mt-2 text-xl font-semibold text-neutral-900">{certificate.course_title}</p>
          <p className="mt-6 text-sm text-neutral-500">
            Completed {new Date(certificate.completed_at).toLocaleDateString()} &middot; Issued{' '}
            {new Date(certificate.issued_at).toLocaleDateString()}
          </p>

          <div className="mt-8 flex items-center justify-center gap-6 border-t border-neutral-200 pt-6">
            <div dangerouslySetInnerHTML={{ __html: qrSvg }} />
            <div className="text-left text-xs text-neutral-500">
              <p className="font-mono">{certificate.certificate_number}</p>
              <p className="mt-1 max-w-[220px]">Scan to verify this certificate&apos;s authenticity at any time.</p>
            </div>
          </div>

          {!certificate.valid && (
            <div className="mt-6 rounded-md bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
              This certificate has been revoked{certificate.revoked_reason ? `: ${certificate.revoked_reason}` : '.'}
            </div>
          )}

          {certificate.valid && (
            <div className="mt-6 flex items-center justify-center gap-3 border-t border-neutral-200 pt-6 print:hidden">
              <span className="text-xs font-medium text-neutral-500">Share:</span>
              <ShareButtons
                url={verifyUrl}
                title={`I completed ${certificate.course_title} at ${certificate.tenant_name}!`}
              />
            </div>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-neutral-400 print:hidden">
          Verified independently at {verifyUrl}
        </p>
      </main>

      <div className="print:hidden">
        <SiteFooter tenantName={config.tenant.name} />
      </div>
    </div>
  );
}
