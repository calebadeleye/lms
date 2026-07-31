import { notFound } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { apiFetch, ApiError } from '@/lib/api-server';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { CourseCurriculum } from '@/components/course-curriculum';
import { EnrollButton } from '@/components/enroll-button';
import { ShareButtons } from '@/components/share-buttons';
import { formatPrice, type CourseDetail } from '@/lib/learning-types';

export default async function CourseDetailPage({ params }: { params: Promise<{ slug: string }> }) {
  const config = BRANDING;

  const { slug } = await params;

  let course: CourseDetail;
  try {
    const body = await apiFetch<{ data: CourseDetail }>(`/api/v1/courses/${slug}`);
    course = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const user = await getOptionalUser();
  const totalLessons = course.modules.reduce((sum, m) => sum + m.lessons.length, 0);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
        <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
          <div>
            <p className="text-xs font-medium text-[var(--brand-primary)]">{course.category?.name}</p>
            <h1 className="mt-1 text-2xl font-bold text-neutral-900 sm:text-3xl">{course.title}</h1>
            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-neutral-500">
              {course.average_rating > 0 && <span>★ {course.average_rating} ({course.reviews_count} reviews)</span>}
              <span className="capitalize">{course.difficulty_level}</span>
              <span>{totalLessons} Lessons</span>
              {course.instructors[0] && <span>By {course.instructors[0].name}</span>}
            </div>

            <div className="mt-4 flex items-center gap-3">
              <span className="text-xs font-medium text-neutral-500">Share:</span>
              <ShareButtons url={`https://${config.domain.primary_hostname}/courses/${course.slug}`} title={course.title} />
            </div>

            {course.thumbnail_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={course.thumbnail_url} alt="" className="mt-6 aspect-video w-full rounded-xl object-cover" />
            ) : (
              <div className="mt-6 aspect-video rounded-xl bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60" />
            )}

            <div className="mt-8">
              <h2 className="text-lg font-semibold text-neutral-900">Overview</h2>
              <p className="mt-2 whitespace-pre-line text-sm text-neutral-600">
                {course.description ?? course.excerpt}
              </p>
            </div>

            <div className="mt-8">
              <h2 className="mb-3 text-lg font-semibold text-neutral-900">Course Curriculum</h2>
              <CourseCurriculum modules={course.modules} isEnrolled={course.is_enrolled} />
            </div>
          </div>

          <aside>
            <div className="sticky top-20 rounded-xl border border-neutral-200 bg-white p-5">
              <p className="text-2xl font-bold text-neutral-900">{formatPrice(course.price_cents, course.currency)}</p>
              <div className="mt-4">
                <EnrollButton
                  courseId={course.id}
                  isFree={course.pricing_type === 'free'}
                  isMembershipOnly={course.pricing_type === 'membership_only'}
                  isEnrolled={course.is_enrolled}
                  isAuthenticated={user !== null}
                />
              </div>
              <ul className="mt-5 space-y-2 text-sm text-neutral-600">
                <li>✓ Lifetime access</li>
                <li>✓ Access on mobile and TV</li>
                <li>✓ Downloadable resources</li>
              </ul>
            </div>
          </aside>
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
