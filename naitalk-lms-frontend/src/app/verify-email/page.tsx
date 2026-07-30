import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { ResendVerificationButton } from '@/components/resend-verification-button';

/**
 * Landing page for both halves of the verification flow: registration
 * redirects here directly (no `status` — "check your inbox"), and the
 * signed link in the email itself points at the backend's
 * `verification.verify` route, which redirects here with `?status=verified`
 * or `?status=invalid` once it's done. The backend has no browser UI of its
 * own, so this page is the only place either outcome is ever shown.
 */
export default async function VerifyEmailPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string }>;
}) {
  const config = BRANDING;

  const { status } = await searchParams;
  const user = await getOptionalUser();

  const content =
    status === 'verified'
      ? { icon: '✅', title: 'Email verified', message: 'Your email address has been verified — you can now use your account.' }
      : status === 'invalid'
        ? { icon: '⚠️', title: 'Link invalid or expired', message: "That verification link isn't valid anymore." }
        : { icon: '📧', title: 'Check your email', message: "We've sent a confirmation link to your email address." };

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="flex flex-1 items-center justify-center px-4 py-20">
        <div className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
          <div className="text-4xl">{content.icon}</div>
          <h1 className="mt-4 text-xl font-bold text-neutral-900">{content.title}</h1>
          <p className="mt-2 text-sm text-neutral-600">
            {content.message}
            {user && status !== 'verified' && (
              <>
                {' '}
                We sent it to <span className="font-medium text-neutral-900">{user.user.email}</span>.
              </>
            )}
          </p>

          <div className="mt-6">
            {status === 'verified' ? (
              <Link
                href="/dashboard"
                className="inline-block rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90"
              >
                Go to Dashboard
              </Link>
            ) : user ? (
              <ResendVerificationButton />
            ) : (
              <Link
                href="/login"
                className="inline-block rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90"
              >
                Log in
              </Link>
            )}
          </div>
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
