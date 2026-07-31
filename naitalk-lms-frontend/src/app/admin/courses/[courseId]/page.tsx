import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { CourseInfoForm } from '@/components/admin/course-info-form';
import { CourseThumbnailUpload } from '@/components/admin/course-thumbnail-upload';
import { AddModuleForm } from '@/components/admin/add-module-form';
import { ModuleSection } from '@/components/admin/module-section';
import { InstructorManager } from '@/components/admin/instructor-manager';
import type { AdminCourseDetail, CourseCategory } from '@/lib/admin-course-types';
import type { TenantMember } from '@/lib/admin-coaching-types';

export default async function AdminCourseBuilderPage({ params }: { params: Promise<{ courseId: string }> }) {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('courses.update')) redirect('/dashboard');

  const { courseId } = await params;

  let course: AdminCourseDetail;
  try {
    const body = await apiFetch<{ data: AdminCourseDetail }>(`/api/v1/admin/courses/${courseId}`);
    course = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const categories = await apiFetch<{ data: CourseCategory[] }>('/api/v1/course-categories');

  // Adding an instructor needs the tenant member roster, which is gated by
  // `users.manage` — a content-manager (who can reach this page via
  // `courses.update`) won't necessarily have that. Degrade gracefully to an
  // empty candidate list rather than failing the whole page.
  let tenantMembers: TenantMember[] = [];
  try {
    const members = await apiFetch<{ data: TenantMember[] }>('/api/v1/admin/members');
    tenantMembers = members.data;
  } catch (error) {
    if (!(error instanceof ApiError && error.status === 403)) throw error;
  }
  const instructorIds = new Set(course.instructors.map((i) => i.id));
  const instructorCandidates = tenantMembers.filter((m) => !instructorIds.has(m.id));

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={adminNav} userName={me.user.name} activeHref="/admin/courses">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/admin/courses" className="text-xs font-medium text-neutral-500 hover:underline">
            ← All courses
          </Link>
          <h1 className="mt-1 text-xl font-bold text-neutral-900">{course.title}</h1>
          <p className="text-sm text-neutral-500">
            {course.status} &middot; {course.enrolments_count} enrolments
          </p>
        </div>
        <Link
          href={`/admin/courses/${course.id}/enrolments`}
          className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium hover:bg-neutral-50"
        >
          View Learners
        </Link>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        <div className="space-y-6">
          <CourseThumbnailUpload courseId={course.id} currentUrl={course.thumbnail_url} />
          <CourseInfoForm course={course} categories={categories.data} />
          <InstructorManager courseId={course.id} instructors={course.instructors} candidates={instructorCandidates} />
        </div>

        <div className="space-y-4">
          <h2 className="text-sm font-semibold text-neutral-900">Curriculum</h2>
          {course.modules.map((module) => (
            <ModuleSection key={module.id} module={module} />
          ))}
          <AddModuleForm courseId={course.id} />
        </div>
      </div>
    </DashboardShell>
  );
}
