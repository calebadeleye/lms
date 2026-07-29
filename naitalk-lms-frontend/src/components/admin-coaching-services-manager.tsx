'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { formatPrice } from '@/lib/learning-types';
import type { AdminCoachingService, AdminCoachingSession } from '@/lib/admin-coaching-types';

const emptyForm = {
  title: '',
  description: '',
  session_type: 'one_to_one' as 'one_to_one' | 'group',
  duration_minutes: '30',
  is_free: true,
  price_cents: '0',
  currency: 'NGN',
  max_participants: '1',
};

/** `datetime-local` inputs read/write in the browser's local time with no
 * timezone info — offsetting by getTimezoneOffset() before formatting gives
 * a `min` that actually means "right now" locally, not "now" in UTC. */
function localDatetimeNow(): string {
  const now = new Date();
  return new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export function AdminCoachingServicesManager({ coachId, initial }: { coachId: number; initial: AdminCoachingService[] }) {
  const router = useRouter();
  const [services, setServices] = useState(initial);
  const [showAddForm, setShowAddForm] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function addService(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    try {
      const res = await fetch(`/api/v1/admin/coaches/${coachId}/services`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: form.title,
          description: form.description || undefined,
          session_type: form.session_type,
          duration_minutes: Number(form.duration_minutes),
          is_free: form.is_free,
          price_cents: form.is_free ? 0 : Number(form.price_cents),
          currency: form.currency,
          max_participants: form.session_type === 'group' ? Number(form.max_participants) : 1,
        }),
      });
      const body = await res.json().catch(() => null);
      if (!res.ok) {
        setError(body?.errors?.title?.[0] ?? 'Could not create this service.');
        return;
      }
      setServices((prev) => [...prev, body.data]);
      setForm(emptyForm);
      setShowAddForm(false);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function deactivate(serviceId: number) {
    await fetch(`/api/v1/admin/coaching-services/${serviceId}`, { method: 'DELETE' });
    setServices((prev) => prev.map((s) => (s.id === serviceId ? { ...s, is_active: false } : s)));
    router.refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-5">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-neutral-900">Coaching Sessions Offered</h2>
        <button onClick={() => setShowAddForm((v) => !v)} className="text-xs font-semibold text-[var(--tenant-primary)] hover:underline">
          {showAddForm ? 'Cancel' : '+ Add session type'}
        </button>
      </div>

      {showAddForm && (
        <form onSubmit={addService} className="mt-3 space-y-3 rounded-lg border border-neutral-200 p-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="block text-xs font-medium text-neutral-700">Title</label>
              <input
                required
                value={form.title}
                onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-neutral-700">Type</label>
              <select
                value={form.session_type}
                onChange={(e) => setForm((f) => ({ ...f, session_type: e.target.value as 'one_to_one' | 'group' }))}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
              >
                <option value="one_to_one">1:1</option>
                <option value="group">Group</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-neutral-700">Description</label>
            <input
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
            />
          </div>

          <div className="grid gap-3 sm:grid-cols-3">
            <div>
              <label className="block text-xs font-medium text-neutral-700">Duration (min)</label>
              <input
                type="number"
                min={5}
                value={form.duration_minutes}
                onChange={(e) => setForm((f) => ({ ...f, duration_minutes: e.target.value }))}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
              />
            </div>
            {form.session_type === 'group' && (
              <div>
                <label className="block text-xs font-medium text-neutral-700">Max participants</label>
                <input
                  type="number"
                  min={1}
                  value={form.max_participants}
                  onChange={(e) => setForm((f) => ({ ...f, max_participants: e.target.value }))}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                />
              </div>
            )}
            <div>
              <label className="flex items-center gap-2 pt-5 text-xs font-medium text-neutral-700">
                <input
                  type="checkbox"
                  checked={form.is_free}
                  onChange={(e) => setForm((f) => ({ ...f, is_free: e.target.checked }))}
                />
                Free
              </label>
            </div>
          </div>

          {!form.is_free && (
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <label className="block text-xs font-medium text-neutral-700">Price (cents)</label>
                <input
                  type="number"
                  min={0}
                  value={form.price_cents}
                  onChange={(e) => setForm((f) => ({ ...f, price_cents: e.target.value }))}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-700">Currency</label>
                <input
                  value={form.currency}
                  onChange={(e) => setForm((f) => ({ ...f, currency: e.target.value.toUpperCase() }))}
                  maxLength={3}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                />
              </div>
            </div>
          )}

          {error && <p className="text-xs text-red-600">{error}</p>}

          <button
            type="submit"
            disabled={pending}
            className="rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
          >
            {pending ? 'Creating…' : 'Create'}
          </button>
        </form>
      )}

      <div className="mt-4 space-y-3">
        {services.map((service) => (
          <ServiceCard key={service.id} service={service} onDeactivate={() => deactivate(service.id)} />
        ))}
        {services.length === 0 && <p className="text-sm text-neutral-500">No coaching sessions offered yet.</p>}
      </div>
    </div>
  );
}

function ServiceCard({ service, onDeactivate }: { service: AdminCoachingService; onDeactivate: () => void }) {
  const [expanded, setExpanded] = useState(false);

  return (
    <div className="rounded-lg border border-neutral-200 p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-sm font-medium text-neutral-900">
            {service.title}{' '}
            <span className="text-xs font-normal text-neutral-500">
              &middot; {service.session_type === 'group' ? 'Group' : '1:1'} &middot; {service.duration_minutes} min
            </span>
          </p>
          {service.description && <p className="mt-1 text-sm text-neutral-600">{service.description}</p>}
          <p className="mt-1 text-xs text-neutral-500">
            {formatPrice(service.price_cents, service.currency)}
            {service.session_type === 'group' && ` · Max ${service.max_participants} participants`}
          </p>
        </div>
        <div className="flex items-center gap-3">
          <span
            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
              service.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-600'
            }`}
          >
            {service.is_active ? 'Active' : 'Inactive'}
          </span>
          {service.session_type === 'group' && (
            <button onClick={() => setExpanded((v) => !v)} className="text-xs font-semibold text-[var(--tenant-primary)] hover:underline">
              {expanded ? 'Hide sessions' : 'Manage sessions'}
            </button>
          )}
          {service.is_active && (
            <button onClick={onDeactivate} className="text-xs font-semibold text-red-600 hover:underline">
              Deactivate
            </button>
          )}
        </div>
      </div>

      {expanded && <GroupSessionScheduler serviceId={service.id} />}
    </div>
  );
}

function GroupSessionScheduler({ serviceId }: { serviceId: number }) {
  const [sessions, setSessions] = useState<AdminCoachingSession[] | null>(null);
  const [scheduledStart, setScheduledStart] = useState('');
  const [meetingUrl, setMeetingUrl] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function loadSessions() {
    const res = await fetch(`/api/v1/coaching-services/${serviceId}/sessions`);
    const body = await res.json();
    setSessions(body.data ?? []);
  }

  useEffect(() => {
    fetch(`/api/v1/coaching-services/${serviceId}/sessions`)
      .then((res) => res.json())
      .then((body) => setSessions(body.data ?? []));
  }, [serviceId]);

  async function scheduleSession(e: React.FormEvent) {
    e.preventDefault();
    if (!scheduledStart) return;
    setPending(true);
    setError(null);
    try {
      const res = await fetch(`/api/v1/admin/coaching-services/${serviceId}/sessions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          scheduled_start: new Date(scheduledStart).toISOString(),
          meeting_url: meetingUrl || undefined,
        }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setError(body?.errors?.scheduled_start?.[0] ?? 'Could not schedule this session.');
        return;
      }
      setScheduledStart('');
      setMeetingUrl('');
      await loadSessions();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="mt-4 border-t border-neutral-100 pt-4">
      <p className="text-xs font-semibold text-neutral-700">Upcoming sessions</p>
      <ul className="mt-2 space-y-1">
        {sessions?.map((session) => (
          <li key={session.id} className="flex items-center justify-between text-sm text-neutral-600">
            <span>{new Date(session.scheduled_start).toLocaleString()}</span>
            <span className="text-xs text-neutral-400">{session.bookings_count} booked</span>
          </li>
        ))}
        {sessions?.length === 0 && <p className="text-sm text-neutral-500">No upcoming sessions scheduled.</p>}
      </ul>

      <form onSubmit={scheduleSession} className="mt-3 flex flex-wrap items-end gap-3">
        <div>
          <label className="block text-xs font-medium text-neutral-700">Schedule a session</label>
          <input
            type="datetime-local"
            required
            min={localDatetimeNow()}
            value={scheduledStart}
            onChange={(e) => setScheduledStart(e.target.value)}
            className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-neutral-700">Meeting URL (optional)</label>
          <input
            value={meetingUrl}
            onChange={(e) => setMeetingUrl(e.target.value)}
            placeholder="https://..."
            className="mt-1 rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </div>
        <button
          type="submit"
          disabled={pending}
          className="rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          Schedule
        </button>
      </form>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
