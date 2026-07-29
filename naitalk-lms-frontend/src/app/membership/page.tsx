import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import { MembershipPlanCard } from '@/components/membership-plan-card';
import type { MembershipPlan, MySubscription } from '@/lib/membership-types';

export default async function MembershipPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const user = await getOptionalUser();
  const plans = await apiFetch<{ data: MembershipPlan[] }>('/api/v1/membership-plans');
  const mySubscription = user
    ? (await apiFetch<{ data: MySubscription | null }>('/api/v1/my/membership')).data
    : null;

  const plansGrid = (
    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {plans.data.map((plan) => (
        <MembershipPlanCard key={plan.id} plan={plan} mySubscription={mySubscription} isAuthenticated={user !== null} />
      ))}
      {plans.data.length === 0 && <p className="col-span-full text-sm text-neutral-500">No membership plans are available yet.</p>}
    </div>
  );

  // Reachable both from the public marketing nav (browsing before signing
  // up) and from the student dashboard sidebar — an already-logged-in
  // visitor should stay inside the dashboard shell, matching every other
  // destination in that sidebar, rather than being dropped onto the
  // public/marketing layout.
  if (user) {
    return (
      <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={user.user.name} activeHref="/membership">
        <h1 className="text-xl font-bold text-neutral-900">Membership</h1>
        <p className="mt-1 text-sm text-neutral-500">
          Unlock members-only courses and more with an active membership.
        </p>
        <div className="mt-6">{plansGrid}</div>
      </DashboardShell>
    );
  }

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={false} />

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-12 sm:px-6">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-neutral-900 sm:text-3xl">Membership Plans</h1>
          <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-600">
            Unlock members-only courses and more with an active membership.
          </p>
        </div>

        <div className="mx-auto mt-10 max-w-4xl">{plansGrid}</div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
