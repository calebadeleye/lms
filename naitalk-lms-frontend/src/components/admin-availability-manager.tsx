'use client';

import { useState } from 'react';
import { DAY_NAMES, type AdminAvailabilityRule } from '@/lib/admin-coaching-types';

export function AdminAvailabilityManager({ coachId, initial }: { coachId: number; initial: AdminAvailabilityRule[] }) {
  const [rules, setRules] = useState(initial);
  const [dayOfWeek, setDayOfWeek] = useState('1');
  const [startTime, setStartTime] = useState('09:00');
  const [endTime, setEndTime] = useState('17:00');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function refresh() {
    const res = await fetch(`/api/v1/admin/coaches/${coachId}/availability`);
    const body = await res.json();
    setRules(body.data ?? []);
  }

  async function addRule(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    try {
      const res = await fetch(`/api/v1/admin/coaches/${coachId}/availability`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ day_of_week: Number(dayOfWeek), start_time: startTime, end_time: endTime }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setError(body?.errors?.end_time?.[0] ?? 'Could not add this availability window.');
        return;
      }
      await refresh();
    } finally {
      setPending(false);
    }
  }

  async function removeRule(ruleId: number) {
    await fetch(`/api/v1/admin/availability/${ruleId}`, { method: 'DELETE' });
    await refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-5">
      <h2 className="text-sm font-semibold text-neutral-900">Availability</h2>

      <ul className="mt-3 divide-y divide-neutral-100">
        {rules.map((rule) => (
          <li key={rule.id} className="flex items-center justify-between py-2 text-sm">
            <span className="text-neutral-700">
              {DAY_NAMES[rule.day_of_week]}: {rule.start_time.slice(0, 5)}–{rule.end_time.slice(0, 5)} ({rule.timezone})
            </span>
            <button onClick={() => removeRule(rule.id)} className="text-xs font-semibold text-red-600 hover:underline">
              Remove
            </button>
          </li>
        ))}
        {rules.length === 0 && <p className="py-2 text-sm text-neutral-500">No availability set yet.</p>}
      </ul>

      <form onSubmit={addRule} className="mt-3 flex flex-wrap items-end gap-3 border-t border-neutral-100 pt-3">
        <div>
          <label className="block text-xs font-medium text-neutral-700">Day</label>
          <select
            value={dayOfWeek}
            onChange={(e) => setDayOfWeek(e.target.value)}
            className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          >
            {DAY_NAMES.map((day, i) => (
              <option key={day} value={i}>
                {day}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Start</label>
          <input
            type="time"
            value={startTime}
            onChange={(e) => setStartTime(e.target.value)}
            className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">End</label>
          <input
            type="time"
            value={endTime}
            onChange={(e) => setEndTime(e.target.value)}
            className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <button
          type="submit"
          disabled={pending}
          className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
        >
          Add
        </button>
      </form>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
