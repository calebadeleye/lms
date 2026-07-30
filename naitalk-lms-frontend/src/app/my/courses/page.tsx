import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';

interface EnrolmentSummary {
  id: number;
  status: string;
  completion_percent: number;
  course: {
    id: number;
    title: string;
    slug: string;
    thumbnail_url: string | null;
    category: { id: number; name: string } | null;
    instructors: { id: number; name: string }[];
  };
}

export default async function MyCoursesPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string }>;
}) {
  const config = BRANDING;

  const me = await requireUser();
  const { tab = 'enrolled' } = await searchParams;

  const enrolments = await apiFetch<{ data: EnrolmentSummary[] }>('/api/v1/my/enrolments');
  const wishlist = await apiFetch<{ data: { course: EnrolmentSummary['course'] }[] }>('/api/v1/my/wishlist');

  const filtered =
    tab === 'completed'
      ? enrolments.data.filter((e) => e.status === 'completed')
      : tab === 'wishlist'
        ? []
        : enrolments.data.filter((e) => e.status !== 'completed');

  const tabs = [
    { key: 'enrolled', label: 'Enrolled' },
    { key: 'completed', label: 'Completed' },
    { key: 'wishlist', label: 'Wishlist' },
  ];

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={me.user.name} activeHref="/my/courses">
      <h1 className="text-xl font-bold text-neutral-900">My Courses</h1>

      <div className="mt-4 flex gap-1 border-b border-neutral-200">
        {tabs.map((t) => (
          <Link
            key={t.key}
            href={`/my/courses?tab=${t.key}`}
            className={`px-4 py-2 text-sm font-medium ${
              tab === t.key
                ? 'border-b-2 border-[var(--brand-primary)] text-[var(--brand-primary)]'
                : 'text-neutral-500 hover:text-neutral-700'
            }`}
          >
            {t.label}
          </Link>
        ))}
      </div>

      {tab === 'wishlist' ? (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {wishlist.data.map(({ course }) => (
            <CourseCard key={course.id} course={course} />
          ))}
          {wishlist.data.length === 0 && <p className="text-sm text-neutral-500">Your wishlist is empty.</p>}
        </div>
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((enrolment) => (
            <CourseCard key={enrolment.id} course={enrolment.course} completionPercent={enrolment.completion_percent} />
          ))}
          {filtered.length === 0 && (
            <p className="text-sm text-neutral-500">
              No {tab === 'completed' ? 'completed' : 'enrolled'} courses yet.{' '}
              <Link href="/courses" className="font-medium text-[var(--brand-primary)] underline">
                Browse courses
              </Link>
              .
            </p>
          )}
        </div>
      )}
    </DashboardShell>
  );
}

function CourseCard({
  course,
  completionPercent,
}: {
  course: EnrolmentSummary['course'];
  completionPercent?: number;
}) {
  return (
    <Link
      href={`/courses/${course.slug}`}
      className="flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white hover:shadow-sm"
    >
      {course.thumbnail_url ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={course.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
      ) : (
        <div className="aspect-video bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60" />
      )}
      <div className="p-4">
        <p className="text-xs text-[var(--brand-primary)]">{course.category?.name}</p>
        <h3 className="mt-1 font-medium text-neutral-900">{course.title}</h3>
        {course.instructors[0] && <p className="mt-1 text-xs text-neutral-500">{course.instructors[0].name}</p>}
        {completionPercent !== undefined && (
          <div className="mt-3">
            <div className="h-1.5 w-full rounded-full bg-neutral-100">
              <div className="h-1.5 rounded-full bg-[var(--brand-accent)]" style={{ width: `${completionPercent}%` }} />
            </div>
            <p className="mt-1 text-xs text-neutral-500">{completionPercent}% complete</p>
          </div>
        )}
      </div>
    </Link>
  );
}
