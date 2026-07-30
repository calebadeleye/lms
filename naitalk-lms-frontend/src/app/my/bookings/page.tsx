import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import { BookingCancelButton } from '@/components/booking-cancel-button';
import type { BookingRecord } from '@/lib/coaching-types';

const STATUS_STYLES: Record<string, string> = {
  confirmed: 'bg-green-100 text-green-700',
  pending: 'bg-amber-100 text-amber-700',
  cancelled: 'bg-neutral-200 text-neutral-700',
  completed: 'bg-neutral-200 text-neutral-700',
  no_show: 'bg-red-100 text-red-700',
};

export default async function MyBookingsPage() {
  const config = BRANDING;

  const me = await requireUser();
  const bookings = await apiFetch<{ data: BookingRecord[] }>('/api/v1/my/bookings');

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={me.user.name} activeHref="/my/bookings">
      <h1 className="text-xl font-bold text-neutral-900">My Coaching Bookings</h1>

      <div className="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        {bookings.data.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">
            You haven&apos;t booked any coaching sessions yet.{' '}
            <Link href="/coaching" className="font-medium text-[var(--brand-primary)] underline">
              Browse coaches
            </Link>
            .
          </p>
        ) : (
          <ul className="divide-y divide-neutral-100">
            {bookings.data.map((booking) => (
              <li key={booking.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                  <p className="text-sm font-medium text-neutral-900">{booking.session.service.title}</p>
                  <p className="mt-0.5 text-xs text-neutral-500">
                    with {booking.session.coach.user.name} &middot; {new Date(booking.session.scheduled_start).toLocaleString()}
                  </p>
                  {booking.session.meeting_url && booking.status === 'confirmed' && (
                    <a
                      href={booking.session.meeting_url}
                      target="_blank"
                      rel="noreferrer"
                      className="mt-1 inline-block text-xs font-medium text-[var(--brand-primary)] underline"
                    >
                      Join meeting
                    </a>
                  )}
                </div>
                <div className="flex items-center gap-3">
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[booking.status] ?? 'bg-neutral-100 text-neutral-600'}`}
                  >
                    {booking.status.replace('_', ' ')}
                  </span>
                  {['confirmed', 'pending'].includes(booking.status) && <BookingCancelButton bookingId={booking.id} />}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </DashboardShell>
  );
}
