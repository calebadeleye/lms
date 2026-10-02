import { notFound } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { formatPrice } from '@/lib/learning-types';
import { OneToOneBookingForm, GroupSessionList } from '@/components/coach-booking';
import { DAY_NAMES, type CoachDetail } from '@/lib/coaching-types';

export default async function CoachProfilePage({ params }: { params: Promise<{ coachId: string }> }) {
  const config = BRANDING;

  const { coachId } = await params;

  let coach: CoachDetail;
  try {
    const body = await apiFetch<{ data: CoachDetail }>(`/api/v1/coaches/${coachId}`);
    coach = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  const user = await getOptionalUser();
  const availabilityByDay = [...coach.availability_rules].sort((a, b) => a.day_of_week - b.day_of_week);

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-12 sm:px-6">
        <div className="flex items-center gap-4">
          <span
            aria-hidden
            className="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-2xl font-bold text-[var(--brand-accent)]"
          >
            {coach.name.charAt(0)}
          </span>
          <div>
            <h1 className="text-xl font-bold text-neutral-900">{coach.name}</h1>
            {coach.title && <p className="text-sm text-neutral-500">{coach.title}</p>}
            {coach.years_experience !== null && (
              <p className="text-xs text-neutral-400">{coach.years_experience} years of experience</p>
            )}
          </div>
        </div>

        {coach.bio && <p className="mt-6 whitespace-pre-line text-sm text-neutral-600">{coach.bio}</p>}

        {availabilityByDay.length > 0 && (
          <div className="mt-6 rounded-xl border border-neutral-200 bg-white p-4">
            <p className="text-xs font-semibold text-neutral-700">Availability ({coach.timezone})</p>
            <ul className="mt-2 space-y-1 text-xs text-neutral-500">
              {availabilityByDay.map((rule) => (
                <li key={rule.id}>
                  {DAY_NAMES[rule.day_of_week]}: {rule.start_time.slice(0, 5)}–{rule.end_time.slice(0, 5)}
                </li>
              ))}
            </ul>
          </div>
        )}

        <h2 className="mt-10 text-lg font-semibold text-neutral-900">Sessions</h2>
        <div className="mt-4 space-y-4">
          {coach.services.map((service) => (
            <div key={service.id} className="rounded-xl border border-neutral-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-neutral-900">{service.title}</p>
                  {service.description && <p className="mt-1 text-sm text-neutral-600">{service.description}</p>}
                  <p className="mt-1 text-xs text-neutral-500">
                    {service.duration_minutes} min &middot; {service.session_type === 'group' ? 'Group session' : '1:1 session'}
                  </p>
                </div>
                <p className="text-sm font-semibold text-neutral-900">{formatPrice(service.price_cents, service.currency)}</p>
              </div>

              {service.session_type === 'one_to_one' ? (
                <OneToOneBookingForm service={service} isAuthenticated={user !== null} />
              ) : (
                <GroupSessionList service={service} isAuthenticated={user !== null} />
              )}
            </div>
          ))}
          {coach.services.length === 0 && <p className="text-sm text-neutral-500">This coach has no active sessions yet.</p>}
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
