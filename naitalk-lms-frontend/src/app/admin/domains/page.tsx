import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { DomainsManager } from '@/components/domains-manager';

interface Domain {
  id: number;
  hostname: string;
  domain_type: string;
  verification_status: string;
  verification_token: string | null;
  is_primary: boolean;
  last_verification_error: string | null;
}

export default async function DomainsPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('domains.manage')) redirect('/dashboard');

  const domains = await apiFetch<{ data: Domain[] }>('/api/v1/tenant/domains');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={tenantAdminNav}
      userName={me.user.name}
      activeHref="/admin/domains"
    >
      <h1 className="text-xl font-bold text-neutral-900">Custom Domains</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Every tenant gets a free subdomain automatically. Add your own domain and verify it with a DNS TXT record.
      </p>

      <div className="mt-6 max-w-2xl">
        <DomainsManager initial={domains.data} />
      </div>
    </DashboardShell>
  );
}
