import { redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { platformNav } from '@/lib/nav';
import { PlatformSubscriptionsManager } from '@/components/platform-subscriptions-manager';
import type { SubscribedTenant } from '@/lib/platform-subscription-types';
import type { PlatformPlan } from '@/lib/platform-plan-types';

export default async function PlatformSubscriptionsPage() {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('subscriptions.manage')) redirect('/platform');

  const [tenants, plans] = await Promise.all([
    apiFetch<{ data: SubscribedTenant[] }>('/api/v1/platform/tenants?per_page=100'),
    apiFetch<{ data: PlatformPlan[] }>('/api/v1/platform/plans'),
  ]);

  return (
    <PlatformShell
      navItems={platformNav}
      userName={me.user.name}
      userEmail={me.user.email}
      activeHref="/platform/subscriptions"
    >
      <PlatformSubscriptionsManager
        initialTenants={tenants.data}
        plans={plans.data}
        canGrantComplimentary={me.permissions.includes('complimentary.grant')}
      />
    </PlatformShell>
  );
}
