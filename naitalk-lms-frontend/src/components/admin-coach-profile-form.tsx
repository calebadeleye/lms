'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminCoach } from '@/lib/admin-coaching-types';

export function AdminCoachProfileForm({ coach }: { coach: AdminCoach }) {
  const router = useRouter();
  const [form, setForm] = useState({
    title: coach.title ?? '',
    bio: coach.bio ?? '',
    years_experience: coach.years_experience?.toString() ?? '',
    timezone: coach.timezone,
    is_active: coach.is_active,
  });
  const [pending, setPending] = useState(false);
  const [saved, setSaved] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setSaved(false);
    try {
      await fetch(`/api/v1/admin/coaches/${coach.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: form.title,
          bio: form.bio,
          years_experience: form.years_experience ? Number(form.years_experience) : null,
          timezone: form.timezone,
          is_active: form.is_active,
        }),
      });
      setSaved(true);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4 rounded-xl border border-neutral-200 bg-white p-5">
      <h2 className="text-sm font-semibold text-neutral-900">Profile</h2>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="block text-xs font-medium text-neutral-700">Title</label>
          <input
            value={form.title}
            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Timezone</label>
          <input
            value={form.timezone}
            onChange={(e) => setForm((f) => ({ ...f, timezone: e.target.value }))}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
      </div>

      <div>
        <label className="block text-xs font-medium text-neutral-700">Bio</label>
        <textarea
          value={form.bio}
          onChange={(e) => setForm((f) => ({ ...f, bio: e.target.value }))}
          rows={3}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
      </div>

      <div className="flex items-end gap-4">
        <div>
          <label className="block text-xs font-medium text-neutral-700">Years of experience</label>
          <input
            type="number"
            min={0}
            value={form.years_experience}
            onChange={(e) => setForm((f) => ({ ...f, years_experience: e.target.value }))}
            className="mt-1 w-32 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <label className="flex items-center gap-2 pb-2 text-sm text-neutral-700">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
          />
          Active (visible in the public coach catalogue)
        </label>
      </div>

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={pending}
          className="rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Saving…' : 'Save changes'}
        </button>
        {saved && <p className="text-sm text-green-600">Saved.</p>}
      </div>
    </form>
  );
}
