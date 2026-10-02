'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function AddModuleForm({ courseId }: { courseId: number }) {
  const router = useRouter();
  const [title, setTitle] = useState('');
  const [pending, setPending] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    try {
      await fetch(`/api/v1/admin/courses/${courseId}/modules`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title }),
      });
      setTitle('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={submit} className="flex gap-2">
      <input
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder="New module title"
        className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
      />
      <button
        type="submit"
        disabled={pending || !title}
        className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
      >
        + Add module
      </button>
    </form>
  );
}
