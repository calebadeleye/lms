import Link from 'next/link';
import { notFound } from 'next/navigation';
import { requireUser } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import type { LessonContent } from '@/lib/learning-types';
import { LessonSidebar } from '@/components/lesson-sidebar';
import { LessonPlayer } from '@/components/lesson-player';
import { LogoutButton } from '@/components/logout-button';

export default async function LearnPage({ params }: { params: Promise<{ lessonId: string }> }) {
  await requireUser();
  const { lessonId } = await params;

  let lesson: LessonContent;
  let lockedMessage: string | null = null;

  try {
    const body = await apiFetch<{ data: LessonContent }>(`/api/v1/lessons/${lessonId}`);
    lesson = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 403) {
      const message = (error.body as { errors?: { message?: string }[] })?.errors?.[0]?.message;
      lockedMessage = message ?? 'This lesson is not available to you yet.';
      lesson = null as unknown as LessonContent;
    } else if (error instanceof ApiError && error.status === 404) {
      notFound();
      return;
    } else {
      throw error;
    }
  }

  if (lockedMessage) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-neutral-50 px-6 text-center">
        <p className="text-lg font-semibold text-neutral-900">Lesson locked</p>
        <p className="max-w-md text-sm text-neutral-600">{lockedMessage}</p>
        <Link href="/my/courses" className="mt-2 text-sm font-medium text-[var(--brand-primary)] underline">
          Back to My Courses
        </Link>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen flex-col bg-neutral-50">
      <header className="flex items-center justify-between border-b border-neutral-200 bg-white px-4 py-3 sm:px-6">
        <Link href={`/courses/${lesson.course_slug}`} className="text-sm font-medium text-neutral-600 hover:text-[var(--brand-primary)]">
          ← {lesson.course_title}
        </Link>
        <LogoutButton className="text-sm font-medium text-neutral-500 hover:text-neutral-700" />
      </header>

      <div className="mx-auto grid w-full max-w-6xl flex-1 gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_280px]">
        <LessonPlayer lesson={lesson} />
        <aside className="order-first lg:order-last">
          <LessonSidebar modules={lesson.modules} />
        </aside>
      </div>
    </div>
  );
}
