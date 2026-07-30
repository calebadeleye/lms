import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';

interface Session {
  id: number;
  device_label: string | null;
  ip_address: string | null;
  user_agent: string | null;
  last_used_at: string | null;
  created_at: string;
}

export default async function AccountPage() {
  const config = BRANDING;

  const me = await requireUser();
  const sessionsBody = await apiFetch<{ data: Session[] }>('/api/v1/auth/sessions');

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={me.user.name} activeHref="/account">
      <h1 className="text-xl font-bold text-neutral-900">Account Settings</h1>

      <section className="mt-6 rounded-xl border border-neutral-200 bg-white p-6">
        <h2 className="text-sm font-semibold text-neutral-900">Profile</h2>
        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs text-neutral-500">Name</dt>
            <dd className="text-sm font-medium text-neutral-900">{me.user.name}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500">Email</dt>
            <dd className="text-sm font-medium text-neutral-900">{me.user.email}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500">Role</dt>
            <dd className="text-sm font-medium text-neutral-900">{me.role?.name ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-neutral-500">Email verified</dt>
            <dd className="text-sm font-medium text-neutral-900">
              {me.user.email_verified_at ? 'Yes' : 'Not yet'}
            </dd>
          </div>
        </dl>
      </section>

      <section className="mt-6 rounded-xl border border-neutral-200 bg-white p-6">
        <h2 className="text-sm font-semibold text-neutral-900">Active sessions</h2>
        <p className="mt-1 text-xs text-neutral-500">Devices currently signed in to your account.</p>
        <ul className="mt-4 divide-y divide-neutral-100">
          {sessionsBody.data.map((session) => (
            <li key={session.id} className="flex items-center justify-between py-3 text-sm">
              <div>
                <p className="font-medium text-neutral-900">{session.device_label ?? 'Unknown device'}</p>
                <p className="text-xs text-neutral-500">
                  {session.ip_address} &middot; last used{' '}
                  {session.last_used_at ? new Date(session.last_used_at).toLocaleString() : 'never'}
                </p>
              </div>
            </li>
          ))}
          {sessionsBody.data.length === 0 && <li className="py-3 text-sm text-neutral-500">No active sessions.</li>}
        </ul>
      </section>
    </DashboardShell>
  );
}
