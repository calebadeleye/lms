import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { AssessmentLeadsManager, type AssessmentLead } from '@/components/admin/assessment-leads-manager';
import type { PaginationMeta } from '@/components/pagination';

export default async function AdminAssessmentLeadsPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('students.manage')) redirect('/dashboard');

  const leads = await apiFetch<{ data: AssessmentLead[]; meta?: { pagination: PaginationMeta } }>(
    '/api/v1/admin/assessment-leads?page=1&per_page=20'
  );

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={adminNav} userName={me.user.name} activeHref="/admin/assessment-leads">
      <h1 className="text-xl font-bold text-neutral-900">Assessment Leads</h1>
      <p className="mt-1 text-sm text-neutral-500">
        People who asked for the free personality assessment. Each one was emailed the assessment link and an
        invitation to the Find Your Career Fit course.
      </p>

      <div className="mt-6">
        <AssessmentLeadsManager
          initial={leads.data}
          initialMeta={leads.meta?.pagination ?? { page: 1, per_page: 20, total: leads.data.length }}
        />
      </div>
    </DashboardShell>
  );
}
