import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { AdminUsersManager } from '@/components/admin-users-manager';
import type { PaginationMeta } from '@/components/pagination';
import type { TenantMemberDetail, TenantRole, PendingInvitation } from '@/lib/admin-users-types';

export default async function AdminUsersPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('users.manage')) redirect('/dashboard');

  const [members, invitations, roles] = await Promise.all([
    apiFetch<{ data: TenantMemberDetail[]; meta?: { pagination: PaginationMeta } }>(
      '/api/v1/admin/tenant-users?page=1&per_page=20'
    ),
    apiFetch<{ data: PendingInvitation[] }>('/api/v1/admin/invitations'),
    apiFetch<{ data: TenantRole[] }>('/api/v1/admin/roles'),
  ]);

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={adminNav} userName={me.user.name} activeHref="/admin/users">
      <h1 className="text-xl font-bold text-neutral-900">Users</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Everyone with access to this admin panel — staff, instructors, coaches. For your learners, see Students.
      </p>

      <div className="mt-6">
        <AdminUsersManager
          initialMembers={members.data}
          initialMeta={members.meta?.pagination ?? { page: 1, per_page: 20, total: members.data.length }}
          initialInvitations={invitations.data}
          roles={roles.data}
          currentUserId={me.user.id}
        />
      </div>
    </DashboardShell>
  );
}
