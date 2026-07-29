'use client';

import { useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { resolveVideoEmbed, type LessonContent } from '@/lib/learning-types';
import { QuizPlayer } from '@/components/quiz-player';
import { AssignmentPlayer } from '@/components/assignment-player';

export function LessonPlayer({ lesson }: { lesson: LessonContent }) {
  const router = useRouter();
  const [status, setStatus] = useState(lesson.progress?.status ?? 'not_started');
  const [marking, setMarking] = useState(false);
  const lastReported = useRef(0);
  const videoEmbed = resolveVideoEmbed(lesson.video_path);

  async function reportPosition(seconds: number) {
    // Throttle to once every 5s of playback to avoid hammering the API.
    if (seconds - lastReported.current < 5) return;
    lastReported.current = seconds;

    const res = await fetch(`/api/v1/lessons/${lesson.id}/progress/position`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ position_seconds: Math.floor(seconds) }),
    });
    const body = await res.json();
    if (res.ok && body.data.status !== status) {
      setStatus(body.data.status);
      router.refresh();
    }
  }

  async function markComplete() {
    setMarking(true);
    try {
      const res = await fetch(`/api/v1/lessons/${lesson.id}/progress/complete`, { method: 'POST' });
      if (res.ok) {
        setStatus('completed');
        router.refresh();
      }
    } finally {
      setMarking(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-bold text-neutral-900">{lesson.title}</h1>
        {status === 'completed' && (
          <span className="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
            ✓ Completed
          </span>
        )}
      </div>

      {lesson.type === 'video' && (
        <>
          {!lesson.video_path ? (
            <div className="grid aspect-video w-full place-items-center rounded-xl bg-black text-sm text-white/60">
              No video added for this lesson yet.
            </div>
          ) : videoEmbed.kind === 'direct' ? (
            <video
              controls
              className="w-full rounded-xl bg-black"
              src={lesson.video_path}
              onTimeUpdate={(e) => reportPosition(e.currentTarget.currentTime)}
              onEnded={(e) => reportPosition(e.currentTarget.duration)}
            />
          ) : (
            // YouTube/Vimeo embeds are cross-origin iframes — there's no way
            // to read playback position from them, so progress here relies
            // on the "Mark as Complete" button below rather than the
            // watch-90%-of-it auto-completion a direct file gets.
            <iframe
              src={videoEmbed.embedUrl}
              title={lesson.title}
              className="aspect-video w-full rounded-xl"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          )}
        </>
      )}

      {lesson.type === 'audio' && (
        <audio
          controls
          className="w-full"
          src={lesson.video_path ?? undefined}
          onTimeUpdate={(e) => reportPosition(e.currentTarget.currentTime)}
          onEnded={(e) => reportPosition(e.currentTarget.duration)}
        />
      )}

      {lesson.type === 'rich_text' && (
        <div className="rounded-xl border border-neutral-200 bg-white p-6">
          <p className="whitespace-pre-line text-sm text-neutral-700">
            {(lesson.content?.body as string) ?? 'No content.'}
          </p>
        </div>
      )}

      {lesson.type === 'file' && (
        <div className="rounded-xl border border-neutral-200 bg-white p-6">
          <p className="text-sm text-neutral-600">Downloadable resource for this lesson.</p>
          {lesson.video_path && (
            <a href={lesson.video_path} className="mt-2 inline-block text-sm font-medium text-[var(--tenant-primary)] underline">
              Download file
            </a>
          )}
        </div>
      )}

      {lesson.type === 'external_link' && (
        <div className="rounded-xl border border-neutral-200 bg-white p-6">
          <p className="text-sm text-neutral-600">This lesson links to an external resource.</p>
          {lesson.content?.url ? (
            <a
              href={lesson.content.url as string}
              target="_blank"
              rel="noreferrer"
              className="mt-2 inline-block text-sm font-medium text-[var(--tenant-primary)] underline"
            >
              Open resource ↗
            </a>
          ) : null}
        </div>
      )}

      {lesson.type === 'live' && (
        <div className="rounded-xl border border-neutral-200 bg-white p-6">
          <p className="text-sm text-neutral-600">This is a live session lesson.</p>
          {lesson.content?.meeting_url ? (
            <a
              href={lesson.content.meeting_url as string}
              target="_blank"
              rel="noreferrer"
              className="mt-2 inline-block text-sm font-medium text-[var(--tenant-primary)] underline"
            >
              Join session ↗
            </a>
          ) : null}
        </div>
      )}

      {lesson.type === 'quiz' && <QuizPlayer lessonId={lesson.id} />}
      {lesson.type === 'assignment' && <AssignmentPlayer lessonId={lesson.id} />}

      {(['rich_text', 'file', 'external_link', 'live'].includes(lesson.type) ||
        (lesson.type === 'video' && videoEmbed.kind !== 'direct')) &&
        status !== 'completed' && (
        <button
          onClick={markComplete}
          disabled={marking}
          className="mt-4 rounded-md bg-[var(--tenant-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {marking ? 'Marking…' : 'Mark as Complete'}
        </button>
      )}
    </div>
  );
}
