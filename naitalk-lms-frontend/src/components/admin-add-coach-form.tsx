'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { TenantMember } from '@/lib/admin-coaching-types';

export function AdminAddCoachForm({ candidates }: { candidates: TenantMember[] }) {
  const router = useRouter();
  const [showForm, setShowForm] = useState(false);
  const [userId, setUserId] = useState(candidates[0]?.id.toString() ?? '');
  const [title, setTitle] = useState('');
  const [timezone, setTimezone] = useState('UTC');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!userId) return;
    setPending(true);
    setError(null);

    try {
      const res = await fetch('/api/v1/admin/coaches', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: Number(userId), title: title || undefined, timezone }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.user_id?.[0] ?? body?.errors?.[0]?.message ?? 'Could not add coach.');
        return;
      }

      setShowForm(false);
      setTitle('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (candidates.length === 0 && !showForm) {
    return <p className="text-sm text-neutral-500">Every tenant member is already a coach.</p>;
  }

  if (!showForm) {
    return (
      <button
        onClick={() => setShowForm(true)}
        className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
      >
        Add coach
      </button>
    );
  }

  return (
    <form onSubmit={submit} className="rounded-xl border border-neutral-200 bg-white p-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <div>
          <label className="block text-xs font-medium text-neutral-700">Tenant member</label>
          <select
            value={userId}
            onChange={(e) => setUserId(e.target.value)}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          >
            {candidates.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name} ({c.role})
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Title (optional)</label>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Career Coach"
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Timezone</label>
          <input
            value={timezone}
            onChange={(e) => setTimezone(e.target.value)}
            placeholder="Africa/Lagos"
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
      </div>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
      <div className="mt-3 flex gap-2">
        <button
          type="submit"
          disabled={pending}
          className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Adding…' : 'Add coach'}
        </button>
        <button type="button" onClick={() => setShowForm(false)} className="text-sm text-neutral-500">
          Cancel
        </button>
      </div>
    </form>
  );
}
