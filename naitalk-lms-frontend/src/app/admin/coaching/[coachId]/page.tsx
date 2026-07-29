import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { AdminCoachProfileForm } from '@/components/admin-coach-profile-form';
import { AdminAvailabilityManager } from '@/components/admin-availability-manager';
import { AdminCoachingServicesManager } from '@/components/admin-coaching-services-manager';
import type { AdminAvailabilityRule, AdminCoach, AdminCoachingService } from '@/lib/admin-coaching-types';

interface CoachWithRelations extends AdminCoach {
  services: AdminCoachingService[];
  availability_rules: AdminAvailabilityRule[];
}

export default async function AdminCoachDetailPage({ params }: { params: Promise<{ coachId: string }> }) {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('coaching.manage')) redirect('/dashboard');

  const { coachId } = await params;

  let coach: CoachWithRelations;
  try {
    const body = await apiFetch<{ data: CoachWithRelations }>(`/api/v1/admin/coaches/${coachId}`);
    coach = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/coaching">
      <h1 className="text-xl font-bold text-neutral-900">{coach.user.name}</h1>
      <p className="mt-1 text-sm text-neutral-500">{coach.user.email}</p>

      <div className="mt-6 max-w-3xl space-y-6">
        <AdminCoachProfileForm coach={coach} />
        <AdminAvailabilityManager coachId={coach.id} initial={coach.availability_rules} />
        <AdminCoachingServicesManager coachId={coach.id} initial={coach.services} />
      </div>
    </DashboardShell>
  );
}
