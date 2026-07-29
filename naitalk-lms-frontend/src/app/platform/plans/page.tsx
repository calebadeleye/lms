import { redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { platformNav } from '@/lib/nav';
import { PlatformPlansManager } from '@/components/platform-plans-manager';
import type { PlatformPlan } from '@/lib/platform-plan-types';

export default async function PlatformPlansPage() {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('plans.manage')) redirect('/platform');

  const plans = await apiFetch<{ data: PlatformPlan[] }>('/api/v1/platform/plans');

  return (
    <PlatformShell navItems={platformNav} userName={me.user.name} userEmail={me.user.email} activeHref="/platform/plans">
      <PlatformPlansManager initialPlans={plans.data} />
    </PlatformShell>
  );
}
