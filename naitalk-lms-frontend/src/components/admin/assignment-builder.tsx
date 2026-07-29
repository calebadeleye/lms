'use client';

import { useState } from 'react';
import type { AdminAssignment } from '@/lib/admin-course-types';

export function AssignmentBuilder({
  lessonId,
  initial,
  onSaved,
}: {
  lessonId: number;
  initial: AdminAssignment | null;
  onSaved: () => void;
}) {
  const [title, setTitle] = useState(initial?.title ?? '');
  const [instructions, setInstructions] = useState(initial?.instructions ?? '');
  const [maxPoints, setMaxPoints] = useState(initial?.max_points ?? 100);
  const [saving, setSaving] = useState(false);

  async function save() {
    setSaving(true);
    try {
      await fetch(`/api/v1/admin/lessons/${lessonId}/assignment`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, instructions, max_points: maxPoints }),
      });
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-3 rounded-md bg-neutral-50 p-4">
      <div>
        <label className="block text-xs font-medium text-neutral-700">Assignment title</label>
        <input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-neutral-700">Instructions</label>
        <textarea
          rows={3}
          value={instructions}
          onChange={(e) => setInstructions(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-neutral-700">Max points</label>
        <input
          type="number"
          min={1}
          value={maxPoints}
          onChange={(e) => setMaxPoints(Number(e.target.value))}
          className="mt-1 w-24 rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
        />
      </div>
      <button
        onClick={save}
        disabled={saving || !title}
        className="rounded-md bg-[var(--tenant-accent)] px-4 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {saving ? 'Saving…' : 'Save assignment'}
      </button>
    </div>
  );
}
