import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { PlanUsageDashboard, type TenantSubscriptionData } from '@/components/admin/plan-usage-dashboard';

export default async function AdminSubscriptionPage() {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('subscription.manage')) redirect('/dashboard');

  const subscription = await apiFetch<{ data: TenantSubscriptionData }>('/api/v1/tenant/subscription');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={tenantAdminNav}
      userName={me.user.name}
      activeHref="/admin/subscription"
    >
      <h1 className="text-xl font-bold text-neutral-900">Plan &amp; Usage</h1>
      <p className="mt-1 text-sm text-neutral-500">Your current plan and how close you are to each limit.</p>

      <div className="mt-6 max-w-2xl">
        <PlanUsageDashboard data={subscription.data} />
      </div>
    </DashboardShell>
  );
}
