'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { formatPrice } from '@/lib/learning-types';
import type { CoachingServiceSummary, CoachingSessionOccurrence } from '@/lib/coaching-types';

/** `datetime-local` inputs read/write in the browser's local time with no
 * timezone info — offsetting by getTimezoneOffset() before formatting gives
 * a `min` that actually means "right now" locally, not "now" in UTC. */
function localDatetimeNow(): string {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

/**
 * After a booking is created it's either already `confirmed` (free
 * services) or `pending` (paid — needs checkout via the coaching-bookings
 * checkout endpoint, same pattern as course/membership checkout).
 */
async function afterBooked(bookingId: number, isFree: boolean, router: ReturnType<typeof useRouter>) {
  if (isFree) {
    router.push('/my/bookings');
    router.refresh();
    return;
  }

  const callback_url = `${window.location.origin}/checkout/callback`;
  const res = await fetch(`/api/v1/checkout/coaching-bookings/${bookingId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ callback_url }),
  });
  const body = await res.json().catch(() => null);
  if (res.ok) {
    window.location.href = body.data.authorization_url;
  }
}

export function OneToOneBookingForm({
  service,
  isAuthenticated,
}: {
  service: CoachingServiceSummary;
  isAuthenticated: boolean;
}) {
  const router = useRouter();
  const [scheduledStart, setScheduledStart] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!isAuthenticated) {
      router.push('/login?redirect=/coaching');
      return;
    }
    if (!scheduledStart) return;

    setPending(true);
    setError(null);

    try {
      const iso = new Date(scheduledStart).toISOString();
      const res = await fetch(`/api/v1/coaching-services/${service.id}/book`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ scheduled_start: iso }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.scheduled_start?.[0] ?? 'Could not book this session. Please try again.');
        return;
      }

      await afterBooked(body.data.id, service.is_free, router);
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="mt-3 flex flex-wrap items-end gap-3">
      <div>
        <label className="block text-xs font-medium text-neutral-700">Pick a date and time</label>
        <input
          type="datetime-local"
          required
          min={localDatetimeNow()}
          value={scheduledStart}
          onChange={(e) => setScheduledStart(e.target.value)}
          className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
      </div>
      <button
        type="submit"
        disabled={pending}
        className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Booking…' : service.is_free ? 'Book — Free' : `Book — ${formatPrice(service.price_cents, service.currency)}`}
      </button>
      {error && <p className="w-full text-xs text-red-600">{error}</p>}
    </form>
  );
}

export function GroupSessionList({
  service,
  isAuthenticated,
}: {
  service: CoachingServiceSummary;
  isAuthenticated: boolean;
}) {
  const router = useRouter();
  const [sessions, setSessions] = useState<CoachingSessionOccurrence[] | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch(`/api/v1/coaching-services/${service.id}/sessions`)
      .then((res) => res.json())
      .then((body) => setSessions(body.data ?? []));
  }, [service.id]);

  async function book(sessionId: number) {
    if (!isAuthenticated) {
      router.push('/login?redirect=/coaching');
      return;
    }

    setPendingId(sessionId);
    setError(null);

    try {
      const res = await fetch(`/api/v1/coaching-sessions/${sessionId}/book`, { method: 'POST' });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.session?.[0] ?? 'Could not book this session. Please try again.');
        return;
      }

      await afterBooked(body.data.id, service.is_free, router);
    } finally {
      setPendingId(null);
    }
  }

  if (sessions === null) {
    return <p className="mt-3 text-xs text-neutral-500">Loading upcoming sessions…</p>;
  }

  if (sessions.length === 0) {
    return <p className="mt-3 text-xs text-neutral-500">No upcoming sessions scheduled yet.</p>;
  }

  return (
    <div className="mt-3 space-y-2">
      {sessions.map((session) => (
        <div key={session.id} className="flex items-center justify-between rounded-md border border-neutral-200 px-3 py-2">
          <span className="text-sm text-neutral-700">{new Date(session.scheduled_start).toLocaleString()}</span>
          <button
            onClick={() => book(session.id)}
            disabled={pendingId === session.id}
            className="rounded-md bg-[var(--brand-accent)] px-3 py-1.5 text-xs font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
          >
            {pendingId === session.id ? 'Booking…' : service.is_free ? 'Book — Free' : `Book — ${formatPrice(service.price_cents, service.currency)}`}
          </button>
        </div>
      ))}
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  );
}
