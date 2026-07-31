import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { formatPrice, type CourseCategory, type CourseSummary } from '@/lib/learning-types';

export default async function CourseCataloguePage({
  searchParams,
}: {
  searchParams: Promise<{ category_id?: string; search?: string }>;
}) {
  const config = BRANDING;

  const user = await getOptionalUser();
  const { category_id, search } = await searchParams;

  const query = new URLSearchParams();
  if (category_id) query.set('category_id', category_id);
  if (search) query.set('search', search);

  const [categories, courses] = await Promise.all([
    apiFetch<{ data: CourseCategory[] }>('/api/v1/course-categories?only_with_published=1'),
    apiFetch<{ data: CourseSummary[] }>(`/api/v1/courses?${query.toString()}`),
  ]);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-neutral-900">Courses</h1>
          <p className="mt-1 text-sm text-neutral-500">Explore our courses and start learning today.</p>
        </div>

        <div className="grid gap-8 lg:grid-cols-[220px_1fr]">
          <aside>
            <form className="mb-6">
              <input
                type="search"
                name="search"
                defaultValue={search}
                placeholder="Search courses…"
                className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
            </form>
            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Categories</h2>
            <ul className="space-y-1 text-sm">
              <li>
                <Link
                  href="/courses"
                  className={`block rounded-md px-2 py-1.5 ${!category_id ? 'bg-[var(--brand-primary)]/10 font-medium text-[var(--brand-primary)]' : 'text-neutral-600 hover:bg-neutral-50'}`}
                >
                  All Categories
                </Link>
              </li>
              {categories.data.map((cat) => (
                <li key={cat.id}>
                  <Link
                    href={`/courses?category_id=${cat.id}`}
                    className={`block rounded-md px-2 py-1.5 ${category_id === String(cat.id) ? 'bg-[var(--brand-primary)]/10 font-medium text-[var(--brand-primary)]' : 'text-neutral-600 hover:bg-neutral-50'}`}
                  >
                    {cat.name}
                  </Link>
                </li>
              ))}
            </ul>
          </aside>

          <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            {courses.data.map((course) => (
              <Link
                key={course.id}
                href={`/courses/${course.slug}`}
                className="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:shadow-md"
              >
                {course.thumbnail_url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={course.thumbnail_url} alt="" className="aspect-video w-full object-cover" />
                ) : (
                  <div className="aspect-video bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60" />
                )}
                <div className="flex flex-1 flex-col p-4">
                  <p className="text-xs font-medium text-[var(--brand-primary)]">{course.category?.name}</p>
                  <h3 className="mt-1 font-semibold text-neutral-900 group-hover:underline">{course.title}</h3>
                  <p className="mt-1 text-xs capitalize text-neutral-500">{course.difficulty_level}</p>
                  <div className="mt-auto flex items-center justify-between pt-4">
                    <span className="text-sm font-bold text-neutral-900">
                      {formatPrice(course.price_cents, course.currency)}
                    </span>
                    {course.average_rating > 0 && (
                      <span className="text-xs text-neutral-500">★ {course.average_rating}</span>
                    )}
                  </div>
                </div>
              </Link>
            ))}
            {courses.data.length === 0 && (
              <p className="col-span-full text-sm text-neutral-500">No courses found.</p>
            )}
          </div>
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
