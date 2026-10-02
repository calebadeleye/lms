'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminCourseInstructor } from '@/lib/admin-course-types';
import type { TenantMember } from '@/lib/admin-coaching-types';

export function InstructorManager({
  courseId,
  instructors,
  candidates,
}: {
  courseId: number;
  instructors: AdminCourseInstructor[];
  candidates: TenantMember[];
}) {
  const router = useRouter();
  const [userId, setUserId] = useState(candidates[0]?.id.toString() ?? '');
  const [role, setRole] = useState<'primary' | 'co_instructor'>('co_instructor');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function addInstructor(e: React.FormEvent) {
    e.preventDefault();
    if (!userId) return;
    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/admin/courses/${courseId}/instructors`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: Number(userId), role }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.user_id?.[0] ?? body?.errors?.[0]?.message ?? 'Could not add instructor.');
        return;
      }

      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function removeInstructor(instructorId: number) {
    if (!window.confirm('Remove this instructor from the course?')) return;
    await fetch(`/api/v1/admin/courses/${courseId}/instructors/${instructorId}`, { method: 'DELETE' });
    router.refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-4">
      <h2 className="text-sm font-semibold text-neutral-900">Instructors</h2>
      <ul className="mt-3 space-y-2">
        {instructors.map((instructor) => (
          <li key={instructor.id} className="flex items-center justify-between text-sm">
            <span>
              {instructor.name}{' '}
              <span className="text-xs text-neutral-400">
                ({instructor.pivot?.role === 'primary' ? 'primary' : 'co-instructor'})
              </span>
            </span>
            <button
              onClick={() => removeInstructor(instructor.id)}
              className="text-xs font-medium text-red-600 hover:underline"
            >
              Remove
            </button>
          </li>
        ))}
        {instructors.length === 0 && <li className="text-sm text-neutral-500">No instructors assigned yet.</li>}
      </ul>

      {candidates.length > 0 && (
        <form onSubmit={addInstructor} className="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">
          <select
            value={userId}
            onChange={(e) => setUserId(e.target.value)}
            className="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          >
            {candidates.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name} ({c.role})
              </option>
            ))}
          </select>
          <select
            value={role}
            onChange={(e) => setRole(e.target.value as 'primary' | 'co_instructor')}
            className="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          >
            <option value="co_instructor">Co-instructor</option>
            <option value="primary">Primary</option>
          </select>
          <button
            type="submit"
            disabled={pending}
            className="rounded-md bg-[var(--brand-accent)] px-3 py-1.5 text-xs font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
          >
            {pending ? 'Adding…' : 'Add'}
          </button>
        </form>
      )}
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
