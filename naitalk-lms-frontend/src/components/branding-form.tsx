'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { uploadWithProgress } from '@/lib/upload-with-progress';

interface BrandingRecord {
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  email_sender_name: string | null;
}

export function BrandingForm({
  initial,
  logoUrl,
  faviconUrl,
  heroImageUrl,
}: {
  initial: BrandingRecord;
  logoUrl: string | null;
  faviconUrl: string | null;
  heroImageUrl: string | null;
}) {
  const router = useRouter();
  const [form, setForm] = useState(initial);
  const [pending, setPending] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setSaved(false);
    setError(null);

    try {
      const res = await fetch('/api/v1/tenant/branding', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        // Send only the fields this form actually owns. `form` state was
        // seeded from the full branding record at page load, which also
        // carries logo_path/favicon_path/hero_image_path — serializing the
        // whole object would resend those as stale nulls (from before an
        // asset was ever uploaded, since uploads refresh the server props
        // but don't resync this already-mounted state) and wipe out
        // whatever was uploaded via the separate upload endpoints.
        body: JSON.stringify({
          primary_color: form.primary_color,
          secondary_color: form.secondary_color,
          accent_color: form.accent_color,
          email_sender_name: form.email_sender_name,
        }),
      });

      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setError(body?.errors?.[0]?.message ?? 'Could not save branding.');
        return;
      }

      setSaved(true);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <AssetUploadField
          label="Logo"
          currentUrl={logoUrl}
          uploadPath="/api/v1/tenant/branding/logo"
          accept="image/png,image/jpeg,image/webp,image/svg+xml"
          onUploaded={() => router.refresh()}
        />
        <AssetUploadField
          label="Favicon"
          currentUrl={faviconUrl}
          uploadPath="/api/v1/tenant/branding/favicon"
          accept="image/png,image/x-icon,image/jpeg"
          onUploaded={() => router.refresh()}
        />
        <AssetUploadField
          label="Homepage hero image"
          currentUrl={heroImageUrl}
          uploadPath="/api/v1/tenant/branding/hero-image"
          accept="image/png,image/jpeg,image/webp"
          onUploaded={() => router.refresh()}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <ColorField
          label="Primary color"
          value={form.primary_color}
          onChange={(v) => setForm((f) => ({ ...f, primary_color: v }))}
        />
        <ColorField
          label="Secondary color"
          value={form.secondary_color}
          onChange={(v) => setForm((f) => ({ ...f, secondary_color: v }))}
        />
        <ColorField
          label="Accent color"
          value={form.accent_color}
          onChange={(v) => setForm((f) => ({ ...f, accent_color: v }))}
        />
      </div>

      <div>
        <label htmlFor="email_sender_name" className="block text-sm font-medium text-neutral-700">
          Email sender name
        </label>
        <input
          id="email_sender_name"
          value={form.email_sender_name ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, email_sender_name: e.target.value }))}
          className="mt-1 w-full max-w-sm rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
      </div>

      {error && <p className="text-sm text-red-600">{error}</p>}
      {saved && <p className="text-sm text-green-600">Saved.</p>}

      <button
        type="submit"
        disabled={pending}
        className="rounded-md bg-[var(--tenant-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Saving…' : 'Save changes'}
      </button>
    </form>
  );
}

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <label className="block text-sm font-medium text-neutral-700">{label}</label>
      <div className="mt-1 flex items-center gap-2">
        <input
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 w-9 cursor-pointer rounded border border-neutral-300"
        />
        <input
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
      </div>
    </div>
  );
}

function AssetUploadField({
  label,
  currentUrl,
  uploadPath,
  accept,
  onUploaded,
}: {
  label: string;
  currentUrl: string | null;
  uploadPath: string;
  accept: string;
  onUploaded: () => void;
}) {
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

      const { ok, body } = await uploadWithProgress(uploadPath, formData, setProgress);

      if (!ok) {
        const errors = (body as { errors?: { file?: string[] } | { message?: string }[] } | null)?.errors;
        const fileError = Array.isArray(errors) ? errors[0]?.message : errors?.file?.[0];
        setError(fileError ?? `Could not upload ${label.toLowerCase()}.`);
        return;
      }

      onUploaded();
    } finally {
      setPending(false);
      e.target.value = '';
    }
  }

  return (
    <div>
      <label className="block text-sm font-medium text-neutral-700">{label}</label>
      <div className="mt-1 flex items-center gap-3">
        {currentUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={currentUrl} alt={label} className="h-12 w-12 rounded border border-neutral-200 object-contain" />
        ) : (
          <div className="flex h-12 w-12 items-center justify-center rounded border border-dashed border-neutral-300 text-[10px] text-neutral-400">
            None
          </div>
        )}
        <div>
          <label className="cursor-pointer rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
            {pending ? `Uploading… ${progress}%` : 'Upload'}
            <input type="file" accept={accept} onChange={handleFile} disabled={pending} className="hidden" />
          </label>
          {pending && (
            <div className="mt-1.5 h-1.5 w-32 overflow-hidden rounded-full bg-neutral-100">
              <div
                className="h-full rounded-full bg-[var(--tenant-primary)] transition-all"
                style={{ width: `${progress}%` }}
              />
            </div>
          )}
        </div>
      </div>
      {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
    </div>
  );
}
