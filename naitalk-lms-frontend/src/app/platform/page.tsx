import { redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { PlatformTenantsManager } from '@/components/platform-tenants-manager';
import { PlatformTenantImport } from '@/components/platform-tenant-import';
import { platformNav } from '@/lib/nav';

interface Tenant {
  id: string;
  name: string;
  slug: string;
  status: string;
}

interface Plan {
  id: number;
  code: string;
  name: string;
}

export default async function PlatformDashboardPage() {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('tenants.manage')) redirect('/platform/plans');

  const [tenants, plans] = await Promise.all([
    apiFetch<{ data: Tenant[] }>('/api/v1/platform/tenants'),
    apiFetch<{ data: Plan[] }>('/api/v1/platform/plans'),
  ]);

  return (
    <PlatformShell navItems={platformNav} userName={me.user.name} userEmail={me.user.email} activeHref="/platform">
      <h1 className="text-xl font-bold text-white">Tenants</h1>
      <p className="mt-1 text-sm text-white/50">Create, suspend, and manage every NAI TALK tenant.</p>

      <div className="mt-6 space-y-6">
        <PlatformTenantsManager initialTenants={tenants.data} plans={plans.data} />
        {me.permissions.includes('exports.manage') && <PlatformTenantImport />}
      </div>
    </PlatformShell>
  );
}
