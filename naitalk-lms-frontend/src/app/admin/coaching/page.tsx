import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { AdminAddCoachForm } from '@/components/admin-add-coach-form';
import type { AdminCoach, TenantMember } from '@/lib/admin-coaching-types';

export default async function AdminCoachingPage() {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('coaching.manage')) redirect('/dashboard');

  const [coaches, members] = await Promise.all([
    apiFetch<{ data: AdminCoach[] }>('/api/v1/admin/coaches'),
    apiFetch<{ data: TenantMember[] }>('/api/v1/admin/tenant-users'),
  ]);

  const coachUserIds = new Set(coaches.data.map((c) => c.user_id));
  const candidates = members.data.filter((m) => !coachUserIds.has(m.id));

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/coaching">
      <h1 className="text-xl font-bold text-neutral-900">Coaching</h1>
      <p className="mt-1 text-sm text-neutral-500">Manage coaches, their availability, and coaching sessions.</p>

      <div className="mt-6 max-w-3xl space-y-4">
        <AdminAddCoachForm candidates={candidates} />

        <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white">
          {coaches.data.length === 0 ? (
            <p className="p-6 text-sm text-neutral-500">No coaches yet.</p>
          ) : (
            <ul className="divide-y divide-neutral-100">
              {coaches.data.map((coach) => (
                <li key={coach.id}>
                  <Link
                    href={`/admin/coaching/${coach.id}`}
                    className="flex items-center justify-between gap-3 px-4 py-4 hover:bg-neutral-50 sm:px-6"
                  >
                    <div>
                      <p className="text-sm font-medium text-neutral-900">
                        {coach.user.name}
                        {coach.title && <span className="text-neutral-500"> &middot; {coach.title}</span>}
                      </p>
                      <p className="mt-0.5 text-xs text-neutral-500">{coach.user.email}</p>
                    </div>
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                        coach.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-600'
                      }`}
                    >
                      {coach.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </DashboardShell>
  );
}
