import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { AdminMembershipsManager } from '@/components/admin-memberships-manager';

interface AdminMembershipPlan {
  id: number;
  name: string;
  billing_period: 'monthly' | 'annual' | 'free';
  price_cents: number;
  currency: string;
  benefits: string[] | null;
  is_active: boolean;
  subscriptions_count: number;
}

export default async function AdminMembershipsPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('memberships.manage')) redirect('/dashboard');

  const plans = await apiFetch<{ data: AdminMembershipPlan[] }>('/api/v1/admin/membership-plans');

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/memberships">
      <h1 className="text-xl font-bold text-neutral-900">Membership Plans</h1>
      <p className="mt-1 text-sm text-neutral-500">Create and manage the membership plans learners can subscribe to.</p>

      <div className="mt-6 max-w-2xl">
        <AdminMembershipsManager initial={plans.data} />
      </div>
    </DashboardShell>
  );
}
