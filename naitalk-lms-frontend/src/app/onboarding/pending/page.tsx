import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { LogoutButton } from '@/components/logout-button';

interface Application {
  status: 'pending' | 'approved' | 'rejected';
  review_note: string | null;
  submitted_at: string;
}

/**
 * Holding page for a registered-but-not-yet-approved member — mirrors the
 * client's manual "an organizer will confirm your grand welcome" process.
 * `requireUser()` redirects here whenever `membership_status !== 'active'`,
 * so this page must use `getOptionalUser()` instead (calling `requireUser()`
 * here would redirect right back to itself).
 */
export default async function OnboardingPendingPage() {
  const config = BRANDING;
  const user = await getOptionalUser();
  if (!user) redirect('/login');

  if (user.membership_status === 'active') redirect('/dashboard');

  const application = await apiFetch<{ data: Application | null }>('/api/v1/auth/application');

  const content =
    user.membership_status === 'rejected'
      ? {
          icon: '💔',
          title: 'Application not approved',
          message:
            application.data?.review_note ??
            "We weren't able to approve your membership application at this time.",
        }
      : {
          icon: '🌱',
          title: "You're on the list!",
          message:
            'Thanks for applying to join. An organizer will review your application and confirm your grand welcome shortly.',
        };

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated />

      <main className="flex flex-1 items-center justify-center px-4 py-20">
        <div className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
          <div className="text-4xl">{content.icon}</div>
          <h1 className="mt-4 text-xl font-bold text-neutral-900">{content.title}</h1>
          <p className="mt-2 text-sm text-neutral-600">{content.message}</p>

          <div className="mt-6">
            <LogoutButton className="inline-block rounded-md border border-neutral-300 px-5 py-2.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50" />
          </div>
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
