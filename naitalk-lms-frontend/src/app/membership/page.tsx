import type { Metadata } from 'next';
import { BRANDING } from '@/lib/branding';
import { HOME_CONTENT } from '@/lib/home-content';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import { MembershipPlanCard } from '@/components/membership-plan-card';
import { MembershipPayModal } from '@/components/membership-pay-modal';
import { MembershipFeeStatus } from '@/components/membership-fee-status';
import type { MembershipPlan, MySubscription } from '@/lib/membership-types';

export const metadata: Metadata = {
  title: 'Membership',
  description:
    'Join HR GEMs Coach Network as a member for free access to a learning community, pro bono ' +
    'coaching opportunities, coaching mentorship, team coaching sessions, and partner discounts.',
};

export default async function MembershipPage({
  searchParams,
}: {
  searchParams: Promise<{ reference?: string; trxref?: string }>;
}) {
  const config = BRANDING;

  const user = await getOptionalUser();

  // Reachable both from the public marketing nav (browsing before signing
  // up) and from the student dashboard sidebar — an already-logged-in
  // visitor should stay inside the dashboard shell, matching every other
  // destination in that sidebar, rather than being dropped onto the
  // public/marketing layout. Only members already inside the dashboard
  // manage a recurring plan; the public page below is the pay-then-register
  // flow for people who don't have an account yet.
  if (user) {
    const plans = await apiFetch<{ data: MembershipPlan[] }>('/api/v1/membership-plans');
    const mySubscription = (await apiFetch<{ data: MySubscription | null }>('/api/v1/my/membership')).data;

    return (
      <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={user.user.name} activeHref="/membership">
        <h1 className="text-xl font-bold text-neutral-900">Membership</h1>
        <p className="mt-1 text-sm text-neutral-500">
          Unlock members-only courses and more with an active membership.
        </p>
        <div className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {plans.data.map((plan) => (
            <MembershipPlanCard key={plan.id} plan={plan} mySubscription={mySubscription} isAuthenticated />
          ))}
          {plans.data.length === 0 && (
            <p className="col-span-full text-sm text-neutral-500">No membership plans are available yet.</p>
          )}
        </div>
      </DashboardShell>
    );
  }

  const params = await searchParams;
  const reference = params.reference ?? params.trxref ?? '';
  const isMembershipFeeReturn = reference.startsWith('membershipfee_');

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={false} />

      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:px-6">
        <div className="text-center">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">Membership</p>
          <h1 className="mt-2 text-2xl font-bold text-neutral-900 sm:text-3xl">Become a Member</h1>
          <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-600">
            Join a thriving community of professionals who learn, grow and support each other to create greater impact.
          </p>
        </div>

        {isMembershipFeeReturn ? (
          <div className="mt-10">
            <MembershipFeeStatus reference={reference} />
          </div>
        ) : (
          <div className="mt-10 grid gap-6 sm:grid-cols-2">
            <div className="rounded-xl border border-neutral-200 bg-white p-6">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-[var(--brand-primary)] text-sm font-bold text-[var(--brand-accent)]">
                1
              </span>
              <h2 className="mt-4 text-base font-bold text-neutral-900">Pay your membership fee</h2>
              <p className="mt-2 text-sm text-neutral-600">
                Click below to pay your one-time membership fee securely via Paystack. You&apos;ll enter your name and email
                in the pay window.
              </p>
              <div className="mt-5">
                <MembershipPayModal className="w-full rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90" />
              </div>
            </div>

            <div className="rounded-xl border border-neutral-200 bg-white p-6">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-[var(--brand-primary)] text-sm font-bold text-[var(--brand-accent)]">
                2
              </span>
              <h2 className="mt-4 text-base font-bold text-neutral-900">Register</h2>
              <p className="mt-2 text-sm text-neutral-600">
                After paying, click below to complete your registration. Be sure to use the{' '}
                <span className="font-semibold">same email address</span> you used for the payment.
              </p>
              <div className="mt-5">
                <a
                  href="/register"
                  className="block w-full rounded-md border border-[var(--brand-primary)] px-5 py-2.5 text-center text-sm font-semibold text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5"
                >
                  Continue to Registration
                </a>
              </div>
            </div>
          </div>
        )}

        {!isMembershipFeeReturn && (
          <div className="mx-auto mt-10 max-w-xl rounded-xl bg-[var(--brand-primary)]/5 p-6">
            <h3 className="text-sm font-bold text-neutral-900">{HOME_CONTENT.membershipBenefits.heading}</h3>
            <ul className="mt-3 space-y-2 text-sm text-neutral-600">
              {HOME_CONTENT.membershipBenefits.items.map((item) => (
                <li key={item.text} className="flex gap-2">
                  <span className="text-[var(--brand-accent)]">✓</span>
                  {item.text}
                </li>
              ))}
            </ul>
          </div>
        )}
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
