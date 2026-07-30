import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { AdminCoursesManager, type AdminCourse } from '@/components/admin-courses-manager';
import { CategoryManager } from '@/components/admin/category-manager';
import type { PaginationMeta } from '@/components/pagination';
import type { CourseCategory } from '@/lib/admin-course-types';

export default async function AdminCoursesPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('courses.update') && !me.permissions.includes('courses.create')) {
    redirect('/dashboard');
  }

  const [courses, categories] = await Promise.all([
    apiFetch<{ data: AdminCourse[]; meta?: { pagination: PaginationMeta } }>('/api/v1/admin/courses?page=1&per_page=20'),
    apiFetch<{ data: CourseCategory[] }>('/api/v1/course-categories'),
  ]);

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={adminNav} userName={me.user.name} activeHref="/admin/courses">
      <h1 className="text-xl font-bold text-neutral-900">Courses</h1>
      <p className="mt-1 text-sm text-neutral-500">Create, publish, and manage your course catalogue.</p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <AdminCoursesManager
          initial={courses.data}
          initialMeta={courses.meta?.pagination ?? { page: 1, per_page: 20, total: courses.data.length }}
          categories={categories.data}
        />
        <CategoryManager initial={categories.data} />
      </div>
    </DashboardShell>
  );
}
