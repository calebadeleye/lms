'use client';

import { useState } from 'react';
import Link from 'next/link';
import type { CourseModuleSummary } from '@/lib/learning-types';

const typeIcon: Record<string, string> = {
  video: '▶',
  rich_text: '📄',
  audio: '🎧',
  file: '📎',
  external_link: '🔗',
  quiz: '❓',
  assignment: '📝',
  live: '📡',
};

function formatDuration(seconds: number | null): string {
  if (!seconds) return '';
  const minutes = Math.round(seconds / 60);
  return `${minutes} min`;
}

export function CourseCurriculum({ modules, isEnrolled }: { modules: CourseModuleSummary[]; isEnrolled: boolean }) {
  const [openModuleId, setOpenModuleId] = useState<number | null>(modules[0]?.id ?? null);

  return (
    <div className="divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-white">
      {modules.map((module, index) => (
        <div key={module.id}>
          <button
            type="button"
            onClick={() => setOpenModuleId(openModuleId === module.id ? null : module.id)}
            className="flex w-full items-center justify-between px-4 py-3 text-left"
          >
            <span className="text-sm font-semibold text-neutral-900">
              Module {index + 1}: {module.title}
            </span>
            <span className="flex items-center gap-2 text-xs text-neutral-500">
              {module.lessons.length} Lessons
              <span aria-hidden>{openModuleId === module.id ? '−' : '+'}</span>
            </span>
          </button>
          {openModuleId === module.id && (
            <ul className="divide-y divide-neutral-100 border-t border-neutral-100">
              {module.lessons.map((lesson) => {
                const clickable = isEnrolled ? !lesson.locked : lesson.is_preview;
                const content = (
                  <div className="flex items-center justify-between px-4 py-2.5 text-sm">
                    <span className="flex items-center gap-2 text-neutral-700">
                      <span aria-hidden>{typeIcon[lesson.type] ?? '•'}</span>
                      {lesson.title}
                      {lesson.is_preview && (
                        <span className="rounded-full bg-[var(--tenant-accent)]/15 px-2 py-0.5 text-[10px] font-semibold text-[var(--tenant-accent)]">
                          Preview
                        </span>
                      )}
                    </span>
                    <span className="flex items-center gap-2 text-xs text-neutral-400">
                      {formatDuration(lesson.duration_seconds)}
                      {!clickable && <span aria-hidden>🔒</span>}
                    </span>
                  </div>
                );

                return (
                  <li key={lesson.id}>
                    {clickable ? (
                      <Link href={`/learn/${lesson.id}`} className="block hover:bg-neutral-50">
                        {content}
                      </Link>
                    ) : (
                      <div className="opacity-60">{content}</div>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      ))}
    </div>
  );
}
