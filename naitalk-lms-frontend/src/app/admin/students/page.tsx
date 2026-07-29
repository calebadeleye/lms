import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { AdminStudentsManager } from '@/components/admin-students-manager';
import type { PaginationMeta } from '@/components/pagination';
import type { AdminStudent } from '@/lib/admin-users-types';

export default async function AdminStudentsPage() {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('students.manage')) redirect('/dashboard');

  const students = await apiFetch<{ data: AdminStudent[]; meta?: { pagination: PaginationMeta } }>(
    '/api/v1/admin/students?page=1&per_page=20'
  );

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/students">
      <h1 className="text-xl font-bold text-neutral-900">Students</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Every learner across all your courses, with enrolment and completion at a glance.
      </p>

      <div className="mt-6">
        <AdminStudentsManager
          initial={students.data}
          initialMeta={students.meta?.pagination ?? { page: 1, per_page: 20, total: students.data.length }}
        />
      </div>
    </DashboardShell>
  );
}
