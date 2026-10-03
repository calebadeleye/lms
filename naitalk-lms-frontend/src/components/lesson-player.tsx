'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { resolveVideoEmbed, type LessonContent, type VideoEmbed } from '@/lib/learning-types';
import { QuizPlayer } from '@/components/quiz-player';
import { AssignmentPlayer } from '@/components/assignment-player';

export function LessonPlayer({ lesson }: { lesson: LessonContent }) {
  const router = useRouter();
  const [status, setStatus] = useState(lesson.progress?.status ?? 'not_started');
  const [marking, setMarking] = useState(false);
  const [markError, setMarkError] = useState<string | null>(null);
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
    setMarkError(null);
    try {
      const res = await fetch(`/api/v1/lessons/${lesson.id}/progress/complete`, { method: 'POST' });
      if (res.ok) {
        setStatus('completed');
        router.refresh();
      } else {
        setMarkError('Could not mark this lesson complete. Please try again.');
      }
    } catch {
      setMarkError('Could not mark this lesson complete. Please try again.');
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
            // YouTube/Vimeo/Drive embeds are cross-origin iframes — there's no
            // way to read playback position from them, so progress here
            // relies on the "Mark as Complete" button below rather than the
            // watch-90%-of-it auto-completion a direct file gets.
            <EmbeddedVideo
              key={videoEmbed.embedUrl}
              embed={videoEmbed}
              title={lesson.title}
              sourceUrl={lesson.video_path}
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
            <a href={lesson.video_path} className="mt-2 inline-block text-sm font-medium text-[var(--brand-primary)] underline">
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
              className="mt-2 inline-block text-sm font-medium text-[var(--brand-primary)] underline"
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
              className="mt-2 inline-block text-sm font-medium text-[var(--brand-primary)] underline"
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
        <div className="mt-4">
          <button
            onClick={markComplete}
            disabled={marking}
            className="rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
          >
            {marking ? 'Marking…' : 'Mark as Complete'}
          </button>
          {markError && <p className="mt-2 text-sm text-red-600">{markError}</p>}
        </div>
      )}
    </div>
  );
}

// Drive's preview document fires onLoad well before its own player has
// finished spinning up, and then shows its own grey spinner for a few more
// seconds. Keeping our (opaque) loader up for this long after onLoad hides
// that second spinner, so there's only ever one loader on screen.
const DRIVE_PLAYER_WARMUP_MS = 3500;

/**
 * Third-party embeds (Google Drive especially) can take several seconds to
 * start and sometimes render blank until the whole page is refreshed — and
 * the iframe's onLoad can't tell us whether the player inside actually
 * worked. So: one opaque loader covering the (still hidden) frame while it
 * loads, a nudge if it's slow, and a "Reload player" control that remounts
 * just the iframe, so a blank player is never a dead end that needs a full
 * page refresh.
 */
function EmbeddedVideo({
  embed,
  title,
  sourceUrl,
}: {
  embed: Exclude<VideoEmbed, { kind: 'direct' }>;
  title: string;
  sourceUrl: string;
}) {
  const [loaded, setLoaded] = useState(false);
  const [revealed, setRevealed] = useState(false);
  const [slow, setSlow] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);

  // Reveal the player once the frame has loaded — immediately for
  // YouTube/Vimeo, after a short warm-up for Drive (see above).
  useEffect(() => {
    if (!loaded) return;
    const timer = setTimeout(() => setRevealed(true), embed.kind === 'google_drive' ? DRIVE_PLAYER_WARMUP_MS : 0);
    return () => clearTimeout(timer);
  }, [loaded, embed.kind, reloadKey]);

  useEffect(() => {
    if (loaded) return;
    const timer = setTimeout(() => setSlow(true), 8000);
    return () => clearTimeout(timer);
  }, [loaded, reloadKey]);

  function reload() {
    setLoaded(false);
    setRevealed(false);
    setSlow(false);
    setReloadKey((k) => k + 1);
  }

  return (
    <div>
      <div className="relative aspect-video w-full overflow-hidden rounded-xl bg-black">
        {!revealed && (
          <div className="absolute inset-0 z-10 grid place-items-center bg-black text-center text-white/80">
            <div>
              <div
                className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-[var(--brand-accent)]"
                aria-hidden
              />
              <p className="mt-3 text-sm" role="status">
                Loading video…
              </p>
              {slow && !loaded && (
                <p className="mt-1 px-4 text-xs text-white/60">
                  This is taking longer than usual. You can reload the player or open the video in a new tab.
                </p>
              )}
            </div>
          </div>
        )}
        <iframe
          key={reloadKey}
          src={embed.embedUrl}
          title={title}
          className={`absolute inset-0 h-full w-full ${revealed ? '' : 'pointer-events-none opacity-0'}`}
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
          onLoad={() => setLoaded(true)}
        />
      </div>
      <p className="mt-2 text-xs text-neutral-500">
        Video not showing or stuck?{' '}
        <button type="button" onClick={reload} className="font-medium text-[var(--brand-primary)] underline">
          Reload player
        </button>
        {' · '}
        <a
          href={sourceUrl}
          target="_blank"
          rel="noreferrer"
          className="font-medium text-[var(--brand-primary)] underline"
        >
          Open in a new tab
        </a>
      </p>
    </div>
  );
}
