import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { TenantExportsManager } from '@/components/tenant-exports-manager';

interface ExportJob {
  id: number;
  status: string;
  file_size_bytes: number | null;
  created_at: string;
}

export default async function TenantExportsPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('exports.request')) redirect('/dashboard');

  const exports = await apiFetch<{ data: ExportJob[] }>('/api/v1/admin/exports');

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/exports">
      <h1 className="text-xl font-bold text-neutral-900">Data Export</h1>
      <p className="mt-1 text-sm text-neutral-500">Request and download a full copy of your academy&apos;s data anytime.</p>

      <div className="mt-6 max-w-2xl">
        <TenantExportsManager initial={exports.data} />
      </div>
    </DashboardShell>
  );
}
