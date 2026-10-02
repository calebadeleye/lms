import Link from 'next/link';
import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';

interface EnrolmentSummary {
  id: number;
  status: string;
  completion_percent: number;
  course: { id: number; title: string; slug: string; thumbnail_url: string | null };
}

export default async function DashboardPage() {
  const config = BRANDING;

  const me = await requireUser();
  // Mirrors /admin's own `permissions.length === 0 -> /dashboard` redirect
  // — anyone with tenant permissions (owner, staff, instructors, coaches)
  // lands on the admin dashboard instead of the plain-student one, since
  // that's the page with capabilities they actually have. A student's
  // `permissions` array is always empty (see PermissionCatalog).
  if (me.permissions.length > 0) redirect('/admin');
  const [enrolments, certificates] = await Promise.all([
    apiFetch<{ data: EnrolmentSummary[] }>('/api/v1/my/enrolments'),
    apiFetch<{ data: unknown[] }>('/api/v1/my/certificates'),
  ]);

  const active = enrolments.data.filter((e) => e.status === 'active');
  const completed = enrolments.data.filter((e) => e.status === 'completed');
  const continueLearning = active.find((e) => e.completion_percent > 0) ?? active[0];

  const stats = [
    { label: 'Enrolled Courses', value: enrolments.data.length },
    { label: 'In Progress', value: active.length },
    { label: 'Completed', value: completed.length },
    { label: 'Certificates Earned', value: certificates.data.length },
  ];

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={me.user.name} activeHref="/dashboard">
      <h1 className="text-xl font-bold text-neutral-900">Dashboard</h1>
      <p className="mt-1 text-sm text-neutral-500">Welcome back, {me.user.name.split(' ')[0]}!</p>

      <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        {stats.map((stat) => (
          <div key={stat.label} className="rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-2xl font-bold text-neutral-900">{stat.value}</p>
            <p className="mt-1 text-xs text-neutral-500">{stat.label}</p>
          </div>
        ))}
      </div>

      {continueLearning ? (
        <div className="mt-6">
          <h2 className="mb-2 text-sm font-semibold text-neutral-900">Continue Learning</h2>
          <Link
            href={`/courses/${continueLearning.course.slug}`}
            className="flex items-center gap-4 rounded-xl border border-neutral-200 bg-white p-4 hover:shadow-sm"
          >
            {continueLearning.course.thumbnail_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={continueLearning.course.thumbnail_url}
                alt=""
                className="h-16 w-24 shrink-0 rounded-md object-cover"
              />
            ) : (
              <div className="h-16 w-24 shrink-0 rounded-md bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60" />
            )}
            <div className="flex-1">
              <p className="font-medium text-neutral-900">{continueLearning.course.title}</p>
              <div className="mt-2 h-1.5 w-full max-w-xs rounded-full bg-neutral-100">
                <div
                  className="h-1.5 rounded-full bg-[var(--brand-accent)]"
                  style={{ width: `${continueLearning.completion_percent}%` }}
                />
              </div>
              <p className="mt-1 text-xs text-neutral-500">{continueLearning.completion_percent}% complete</p>
            </div>
          </Link>
        </div>
      ) : (
        <div className="mt-6 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center">
          <p className="text-sm text-neutral-500">
            You&apos;re not enrolled in any courses yet.{' '}
            <Link href="/courses" className="font-medium text-[var(--brand-primary)] underline">
              Browse the catalogue
            </Link>
            .
          </p>
        </div>
      )}

    </DashboardShell>
  );
}
