import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import { formatPrice } from '@/lib/learning-types';
import type { CoachSummary } from '@/lib/coaching-types';

export default async function CoachingCataloguePage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const user = await getOptionalUser();
  const coaches = await apiFetch<{ data: CoachSummary[] }>('/api/v1/coaches');

  const coachesGrid = (
    <div className="grid gap-6 sm:grid-cols-2">
      {coaches.data.map((coach) => (
        <CoachCard key={coach.id} coach={coach} />
      ))}
      {coaches.data.length === 0 && <p className="col-span-full text-sm text-neutral-500">No coaches are available yet.</p>}
    </div>
  );

  // Reachable both from the public marketing nav and from "Browse coaches"
  // on the student's own bookings page — an already-logged-in visitor
  // should stay inside the dashboard shell rather than being dropped onto
  // the public/marketing layout.
  if (user) {
    return (
      <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={user.user.name} activeHref="/my/bookings">
        <h1 className="text-xl font-bold text-neutral-900">Coaching</h1>
        <p className="mt-1 text-sm text-neutral-500">Book one-on-one or group sessions with our coaches.</p>
        <div className="mt-6 max-w-4xl">{coachesGrid}</div>
      </DashboardShell>
    );
  }

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={false} />

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-12 sm:px-6">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-neutral-900 sm:text-3xl">Coaching</h1>
          <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-600">
            Book one-on-one or group sessions with our coaches.
          </p>
        </div>

        <div className="mx-auto mt-10 max-w-4xl">{coachesGrid}</div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}

function CoachCard({ coach }: { coach: CoachSummary }) {
  return (
    <Link href={`/coaching/${coach.id}`} className="rounded-xl border border-neutral-200 bg-white p-6 hover:shadow-sm">
      <div className="flex items-center gap-3">
        <span
          aria-hidden
          className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-[var(--tenant-primary)] text-lg font-bold text-[var(--tenant-accent)]"
        >
          {coach.name.charAt(0)}
        </span>
        <div>
          <p className="font-semibold text-neutral-900">{coach.name}</p>
          {coach.title && <p className="text-xs text-neutral-500">{coach.title}</p>}
        </div>
      </div>
      {coach.bio && <p className="mt-3 line-clamp-2 text-sm text-neutral-600">{coach.bio}</p>}
      <div className="mt-4 flex flex-wrap gap-2">
        {coach.services.slice(0, 3).map((service) => (
          <span key={service.id} className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs text-neutral-600">
            {service.title} &middot; {formatPrice(service.price_cents, service.currency)}
          </span>
        ))}
      </div>
    </Link>
  );
}
