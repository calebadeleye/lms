import { notFound, redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { platformNav } from '@/lib/nav';
import { PlatformTenantOffboarding } from '@/components/platform-tenant-offboarding';

interface TenantDetail {
  id: string;
  name: string;
  slug: string;
  status: string;
  support_email: string | null;
  deletion_scheduled_at: string | null;
  suspended_at: string | null;
  suspension_reason: string | null;
  domains: { hostname: string; is_primary: boolean }[];
  owner: { id: number; name: string; email: string } | null;
  subscriptions: { status: string; plan?: { name: string } }[];
}

interface ExportJob {
  id: number;
  status: string;
  file_size_bytes: number | null;
  created_at: string;
  completed_at: string | null;
}

const STATUS_STYLES: Record<string, string> = {
  active: 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]',
  suspended: 'bg-red-500/15 text-red-400',
  deletion_scheduled: 'bg-amber-500/15 text-amber-400',
};

export default async function PlatformTenantDetailPage({ params }: { params: Promise<{ tenantId: string }> }) {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('tenants.manage')) redirect('/platform/plans');

  const { tenantId } = await params;

  let tenant: TenantDetail;
  try {
    const body = await apiFetch<{ data: TenantDetail }>(`/api/v1/platform/tenants/${tenantId}`);
    tenant = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const exports = me.permissions.includes('exports.manage')
    ? await apiFetch<{ data: ExportJob[] }>(`/api/v1/platform/tenants/${tenantId}/exports`)
    : { data: [] };

  return (
    <PlatformShell navItems={platformNav} userName={me.user.name} userEmail={me.user.email} activeHref="/platform">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-white">{tenant.name}</h1>
          <p className="mt-1 text-sm text-white/50">
            {tenant.domains.find((d) => d.is_primary)?.hostname ?? tenant.slug}
          </p>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${STATUS_STYLES[tenant.status] ?? 'bg-white/10 text-white/60'}`}>
          {tenant.status.replace('_', ' ')}
        </span>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
        <div>
          {me.permissions.includes('exports.manage') || me.permissions.includes('deletions.manage') ? (
            <PlatformTenantOffboarding
              tenantId={tenant.id}
              status={tenant.status}
              deletionScheduledAt={tenant.deletion_scheduled_at}
              initialExports={exports.data}
            />
          ) : (
            <p className="text-sm text-white/50">You don&apos;t have permission to manage exports or deletions.</p>
          )}
        </div>

        <aside className="space-y-4">
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
            <h2 className="text-sm font-semibold text-white">Owner</h2>
            <p className="mt-2 text-sm text-white/60">{tenant.owner ? `${tenant.owner.name} · ${tenant.owner.email}` : '—'}</p>
          </div>
          <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
            <h2 className="text-sm font-semibold text-white">Subscription</h2>
            <p className="mt-2 text-sm text-white/60">
              {tenant.subscriptions[0] ? `${tenant.subscriptions[0].plan?.name ?? '—'} · ${tenant.subscriptions[0].status}` : '—'}
            </p>
          </div>
          {tenant.suspension_reason && (
            <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-5">
              <h2 className="text-sm font-semibold text-red-300">Suspension reason</h2>
              <p className="mt-2 text-sm text-red-200">{tenant.suspension_reason}</p>
            </div>
          )}
        </aside>
      </div>
    </PlatformShell>
  );
}
