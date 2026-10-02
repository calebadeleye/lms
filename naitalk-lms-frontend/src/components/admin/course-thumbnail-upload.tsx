'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { uploadWithProgress } from '@/lib/upload-with-progress';

export function CourseThumbnailUpload({ courseId, currentUrl }: { courseId: number; currentUrl: string | null }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);

  async function handleFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    setPending(true);
    setProgress(0);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('file', file);

      const { ok, body } = await uploadWithProgress(`/api/v1/admin/courses/${courseId}/thumbnail`, formData, setProgress);

      if (!ok) {
        const errors = (body as { errors?: { file?: string[] } } | null)?.errors;
        setError(errors?.file?.[0] ?? 'Could not upload thumbnail.');
        return;
      }

      router.refresh();
    } finally {
      setPending(false);
      e.target.value = '';
    }
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-5">
      <label className="block text-xs font-medium text-neutral-700">Course thumbnail</label>
      <p className="mt-0.5 text-xs text-neutral-400">Shown on the course catalogue and course cards. Recommended 16:9.</p>

      <label className="mt-3 block cursor-pointer">
        {currentUrl && !pending ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={currentUrl} alt="" className="aspect-video w-full rounded-lg object-cover" />
        ) : (
          <div className="grid aspect-video w-full place-items-center rounded-lg border-2 border-dashed border-neutral-300 bg-neutral-50 text-sm text-neutral-400 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">
            {pending ? `Uploading… ${progress}%` : 'Click to upload an image'}
          </div>
        )}
        <input type="file" accept="image/png,image/jpeg,image/webp" onChange={handleFile} disabled={pending} className="hidden" />
      </label>

      {pending && (
        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
          <div className="h-full rounded-full bg-[var(--brand-accent)] transition-all" style={{ width: `${progress}%` }} />
        </div>
      )}

      {currentUrl && !pending && (
        <label className="mt-2 inline-block cursor-pointer text-xs font-medium text-[var(--brand-primary)] hover:underline">
          Replace image
          <input type="file" accept="image/png,image/jpeg,image/webp" onChange={handleFile} disabled={pending} className="hidden" />
        </label>
      )}

      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
