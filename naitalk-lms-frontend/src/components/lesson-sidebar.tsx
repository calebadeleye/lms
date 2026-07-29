import Link from 'next/link';
import type { LessonModuleNav } from '@/lib/learning-types';

const typeIcon: Record<string, string> = {
  video: '▶', rich_text: '📄', audio: '🎧', file: '📎',
  external_link: '🔗', quiz: '❓', assignment: '📝', live: '📡',
};

export function LessonSidebar({ modules }: { modules: LessonModuleNav[] }) {
  return (
    <nav className="divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-white">
      {modules.map((module) => (
        <div key={module.id}>
          <p className="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">{module.title}</p>
          <ul>
            {module.lessons.map((lesson) => (
              <li key={lesson.id}>
                <Link
                  href={`/learn/${lesson.id}`}
                  className={`flex items-center gap-2 px-4 py-2 text-sm ${
                    lesson.is_current
                      ? 'bg-[var(--tenant-primary)]/10 font-medium text-[var(--tenant-primary)]'
                      : 'text-neutral-600 hover:bg-neutral-50'
                  }`}
                >
                  <span aria-hidden>{typeIcon[lesson.type] ?? '•'}</span>
                  {lesson.title}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  );
}
