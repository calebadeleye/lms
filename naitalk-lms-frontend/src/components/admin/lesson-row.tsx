'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminLesson } from '@/lib/admin-course-types';
import { QuizBuilder } from '@/components/admin/quiz-builder';
import { AssignmentBuilder } from '@/components/admin/assignment-builder';

const lessonTypes = ['video', 'rich_text', 'audio', 'file', 'external_link', 'quiz', 'assignment', 'live'] as const;

export function LessonRow({ lesson }: { lesson: AdminLesson }) {
  const router = useRouter();
  const [expanded, setExpanded] = useState(false);
  const [title, setTitle] = useState(lesson.title);
  const [type, setType] = useState(lesson.type);
  const [isPreview, setIsPreview] = useState(lesson.is_preview);
  const [isMandatory, setIsMandatory] = useState(lesson.is_mandatory);
  const [bodyText, setBodyText] = useState(lesson.content?.body ?? '');
  const [videoPath, setVideoPath] = useState(lesson.video_path ?? '');
  const [linkUrl, setLinkUrl] = useState(lesson.content?.url ?? '');
  const [meetingUrl, setMeetingUrl] = useState(lesson.content?.meeting_url ?? '');
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [materialFilename, setMaterialFilename] = useState(lesson.content?.material_filename ?? null);

  async function saveLesson() {
    setSaving(true);
    try {
      const content =
        type === 'rich_text'
          ? { body: bodyText }
          : type === 'external_link'
            ? { url: linkUrl }
            : type === 'live'
              ? { meeting_url: meetingUrl }
              : lesson.content;

      await fetch(`/api/v1/admin/lessons/${lesson.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title,
          type,
          is_preview: isPreview,
          is_mandatory: isMandatory,
          content,
          ...((type === 'video' || type === 'audio') && { video_path: videoPath }),
        }),
      });
      router.refresh();
    } finally {
      setSaving(false);
    }
  }

  async function deleteLesson() {
    if (!window.confirm(`Delete lesson "${lesson.title}"?`)) return;
    await fetch(`/api/v1/admin/lessons/${lesson.id}`, { method: 'DELETE' });
    router.refresh();
  }

  async function uploadMaterial(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading(true);
    setUploadError(null);

    try {
      const formData = new FormData();
      formData.append('file', file);

      const res = await fetch(`/api/v1/admin/lessons/${lesson.id}/material`, { method: 'POST', body: formData });

      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setUploadError(body?.errors?.file?.[0] ?? body?.errors?.[0]?.message ?? 'Could not upload the file.');
        return;
      }

      setMaterialFilename(file.name);
      router.refresh();
    } finally {
      setUploading(false);
      e.target.value = '';
    }
  }

  return (
    <div className="border-t border-neutral-100 first:border-t-0">
      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        className="flex w-full items-center justify-between px-3 py-2 text-left text-sm"
      >
        <span className="text-neutral-700">
          {lesson.title} <span className="text-xs text-neutral-400">({lesson.type})</span>
        </span>
        <span aria-hidden className="text-neutral-400">{expanded ? '−' : '+'}</span>
      </button>

      {expanded && (
        <div className="space-y-3 border-t border-neutral-100 bg-neutral-50/50 px-3 py-3">
          <div className="grid gap-2 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-medium text-neutral-700">Title</label>
              <input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-700">Type</label>
              <select
                value={type}
                onChange={(e) => setType(e.target.value as AdminLesson['type'])}
                className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm"
              >
                {lessonTypes.map((t) => (
                  <option key={t} value={t}>
                    {t.replace('_', ' ')}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {type === 'rich_text' && (
            <div>
              <label className="block text-xs font-medium text-neutral-700">Body</label>
              <textarea
                rows={3}
                value={bodyText}
                onChange={(e) => setBodyText(e.target.value)}
                className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
              />
            </div>
          )}

          {(type === 'video' || type === 'audio') && (
            <div>
              <label className="block text-xs font-medium text-neutral-700">
                {type === 'video' ? 'Video URL' : 'Audio URL'}
              </label>
              <input
                value={videoPath}
                onChange={(e) => setVideoPath(e.target.value)}
                placeholder="https://www.youtube.com/watch?v=... or a direct file link"
                className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
              />
              <p className="mt-1 text-xs text-neutral-400">
                Paste a link from YouTube (unlisted works fine), Vimeo, Google Drive (must be shared as &quot;Anyone
                with the link&quot;), or any direct-hosted file — not an upload here.
              </p>
            </div>
          )}

          {type === 'external_link' && (
            <div>
              <label className="block text-xs font-medium text-neutral-700">Link URL</label>
              <input
                value={linkUrl}
                onChange={(e) => setLinkUrl(e.target.value)}
                placeholder="https://..."
                className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
              />
            </div>
          )}

          {type === 'live' && (
            <div>
              <label className="block text-xs font-medium text-neutral-700">Meeting URL</label>
              <input
                value={meetingUrl}
                onChange={(e) => setMeetingUrl(e.target.value)}
                placeholder="https://zoom.us/j/... or Google Meet link"
                className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
              />
            </div>
          )}

          {type === 'file' && (
            <div>
              <label className="block text-xs font-medium text-neutral-700">Course material</label>
              <div className="mt-1 flex items-center gap-3">
                <span className="text-sm text-neutral-600">
                  {materialFilename ?? <span className="text-neutral-400">No file uploaded yet</span>}
                </span>
                <label className="cursor-pointer rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                  {uploading ? 'Uploading…' : materialFilename ? 'Replace file' : 'Upload file'}
                  <input
                    type="file"
                    accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.zip"
                    onChange={uploadMaterial}
                    disabled={uploading}
                    className="hidden"
                  />
                </label>
              </div>
              <p className="mt-1 text-xs text-neutral-400">PDF, PowerPoint, Word, Excel, or a zip — up to 20MB.</p>
              {uploadError && <p className="mt-1 text-xs text-red-600">{uploadError}</p>}
            </div>
          )}

          <div className="flex gap-4 text-xs text-neutral-700">
            <label className="flex items-center gap-1.5">
              <input type="checkbox" checked={isPreview} onChange={(e) => setIsPreview(e.target.checked)} />
              Free preview
            </label>
            <label className="flex items-center gap-1.5">
              <input type="checkbox" checked={isMandatory} onChange={(e) => setIsMandatory(e.target.checked)} />
              Mandatory for completion
            </label>
          </div>

          <div className="flex gap-3">
            <button
              onClick={saveLesson}
              disabled={saving}
              className="rounded-md bg-[var(--tenant-primary)] px-4 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >
              {saving ? 'Saving…' : 'Save lesson'}
            </button>
            <button onClick={deleteLesson} className="text-xs font-medium text-red-600">
              Delete lesson
            </button>
          </div>

          {type === 'quiz' && (
            <QuizBuilder lessonId={lesson.id} initial={lesson.quiz?.questions ?? []} onSaved={() => router.refresh()} />
          )}
          {type === 'assignment' && (
            <AssignmentBuilder lessonId={lesson.id} initial={lesson.assignment} onSaved={() => router.refresh()} />
          )}
        </div>
      )}
    </div>
  );
}
