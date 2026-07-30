import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { ApplicationsQueue, type MembershipApplication } from '@/components/admin/applications-queue';

export default async function AdminApplicationsPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('members.approve')) redirect('/dashboard');

  const applications = await apiFetch<{ data: MembershipApplication[] }>('/api/v1/admin/applications?status=pending');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={adminNav}
      userName={me.user.name}
      activeHref="/admin/applications"
    >
      <h1 className="text-xl font-bold text-neutral-900">Membership Applications</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Review new sign-ups before they get access to courses and the community — this replaces the
        old manual Google Form confirmation.
      </p>

      <div className="mt-6">
        <ApplicationsQueue initial={applications.data} />
      </div>
    </DashboardShell>
  );
}
