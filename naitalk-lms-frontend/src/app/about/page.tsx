import type { Metadata } from 'next';
import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { HOME_CONTENT } from '@/lib/home-content';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { PhotoSlotImage } from '@/components/home/photo-slot';

export const metadata: Metadata = {
  title: 'About Us',
  description:
    'HR GEMs means Great.Excellent.Minds. Learn how HR GEMs Coach Network transforms HR ' +
    'professionals into change agents through coaching, NLP, and CBT-based transformational skills.',
};

export default async function AboutPage() {
  const config = BRANDING;
  const user = await getOptionalUser();
  const { whoWeAre } = HOME_CONTENT;

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated={user !== null} />

      <main className="flex-1">
        <section className="mx-auto max-w-4xl px-4 py-14 text-center sm:px-6">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{whoWeAre.eyebrow}</p>
          <h1 className="mt-2 text-3xl font-bold text-[var(--brand-primary)] sm:text-4xl">{whoWeAre.heading}</h1>
          <p className="mt-3 text-sm font-semibold text-neutral-700">{whoWeAre.meaning}</p>
        </section>

        <section className="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-14 sm:px-6 lg:grid-cols-2">
          <PhotoSlotImage photo={whoWeAre.photo} className="aspect-4/3 w-full rounded-2xl" />
          <div className="space-y-4 text-sm text-neutral-600">
            {whoWeAre.paragraphs.map((paragraph, i) => (
              <p key={i}>{paragraph}</p>
            ))}
            <Link
              href="/register"
              className="inline-block rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-neutral-900 hover:opacity-90"
            >
              Join HR GEMs &rarr;
            </Link>
          </div>
        </section>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
