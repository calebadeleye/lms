'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
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

export function CourseCurriculum({
  modules,
  isEnrolled,
  courseId,
  personalityTypeModuleId,
  personalityTypeOptions,
  myPersonalityType,
}: {
  modules: CourseModuleSummary[];
  isEnrolled: boolean;
  courseId: number;
  personalityTypeModuleId: number | null;
  personalityTypeOptions: string[];
  myPersonalityType: string | null;
}) {
  const [openModuleId, setOpenModuleId] = useState<number | null>(modules[0]?.id ?? null);
  const [isChangingType, setIsChangingType] = useState(false);

  return (
    <div className="divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-white">
      {modules.map((module, index) => {
        const isPersonalityModule = isEnrolled && module.id === personalityTypeModuleId;
        const showPicker = isPersonalityModule && (!myPersonalityType || isChangingType);

        return (
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
                {isPersonalityModule && !myPersonalityType
                  ? 'Select yours'
                  : `${module.lessons.length} ${module.lessons.length === 1 ? 'Lesson' : 'Lessons'}`}
                <span aria-hidden>{openModuleId === module.id ? '−' : '+'}</span>
              </span>
            </button>
            {openModuleId === module.id && (
              <>
                <ul className="divide-y divide-neutral-100 border-t border-neutral-100">
                  {module.lessons.map((lesson) => {
                    const clickable = isEnrolled ? !lesson.locked : lesson.is_preview;
                    const content = (
                      <div className="flex items-center justify-between px-4 py-2.5 text-sm">
                        <span className="flex items-center gap-2 text-neutral-700">
                          <span aria-hidden>{typeIcon[lesson.type] ?? '•'}</span>
                          {lesson.title}
                          {lesson.is_preview && (
                            <span className="rounded-full bg-[var(--brand-accent)]/15 px-2 py-0.5 text-[10px] font-semibold text-[var(--brand-accent)]">
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
                  {isPersonalityModule && myPersonalityType && !isChangingType && (
                    <li className="px-4 py-2 text-right">
                      <button
                        type="button"
                        onClick={() => setIsChangingType(true)}
                        className="text-xs font-medium text-[var(--brand-primary)] underline"
                      >
                        Picked the wrong type? Change it
                      </button>
                    </li>
                  )}
                </ul>
                {showPicker && (
                  <div className="border-t border-neutral-100 px-4 py-4">
                    <PersonalityTypePicker
                      courseId={courseId}
                      options={personalityTypeOptions}
                      initialValue={myPersonalityType}
                      onSaved={() => setIsChangingType(false)}
                      onCancel={myPersonalityType ? () => setIsChangingType(false) : undefined}
                    />
                  </div>
                )}
              </>
            )}
          </div>
        );
      })}
    </div>
  );
}

function PersonalityTypePicker({
  courseId,
  options,
  initialValue,
  onSaved,
  onCancel,
}: {
  courseId: number;
  options: string[];
  initialValue: string | null;
  onSaved: () => void;
  onCancel?: () => void;
}) {
  const router = useRouter();
  const [selected, setSelected] = useState(initialValue ?? '');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (!selected) return;

    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/courses/${courseId}/personality-type`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ personality_type: selected }),
      });

      if (!res.ok) {
        setError('Could not save your selection. Please try again.');
        return;
      }

      onSaved();
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div>
      <p className="text-sm text-neutral-600">
        Which personality type is yours? You&apos;ll only watch that one video.
      </p>
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <select
          value={selected}
          onChange={(e) => setSelected(e.target.value)}
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
        >
          <option value="">Choose your type…</option>
          {options.map((code) => (
            <option key={code} value={code}>
              {code}
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={handleSubmit}
          disabled={!selected || pending}
          className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Saving…' : 'Confirm'}
        </button>
        {onCancel && (
          <button type="button" onClick={onCancel} className="text-sm text-neutral-500 underline">
            Cancel
          </button>
        )}
      </div>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
