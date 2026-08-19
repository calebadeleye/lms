import type { Metadata } from 'next';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { EVENTS, eventImageUrls } from '@/lib/events-content';
import { EventImage } from '@/components/events/event-image';

export const metadata: Metadata = {
  title: 'Events',
  description: 'Photos from HR GEMs Coach Network events, from the most recent to the earliest.',
};

export default async function EventsPage() {
  const config = BRANDING;
  const user = await getOptionalUser();

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-12 sm:px-6">
        <div className="text-center">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">Events</p>
          <h1 className="mt-2 text-2xl font-bold text-neutral-900 sm:text-3xl">HR GEMs Events</h1>
          <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-600">
            A look back at our gatherings, from the most recent to the earliest.
          </p>
        </div>

        <div className="mt-10 space-y-10">
          {EVENTS.map((event) => (
            <EventCard key={event.title} title={event.title} year={event.year} images={eventImageUrls(event)} driveUrl={event.driveUrl} />
          ))}
        </div>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}

function EventCard({ title, year, images, driveUrl }: { title: string; year: number; images: string[]; driveUrl: string }) {
  return (
    <article className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
      <div className={`grid gap-1 ${images.length > 1 ? 'grid-cols-2' : 'grid-cols-1'}`}>
        {images.map((src, i) => (
          <EventImage key={src} src={src} alt={`${title} photo ${i + 1}`} className="h-56 w-full object-cover sm:h-72" />
        ))}
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 p-6">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{year}</p>
          <h2 className="mt-1 text-lg font-bold text-neutral-900">{title}</h2>
        </div>
        <a
          href={driveUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 rounded-md border border-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5"
        >
          See more &rarr;
        </a>
      </div>
    </article>
  );
}
