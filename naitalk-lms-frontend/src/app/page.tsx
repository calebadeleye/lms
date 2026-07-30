import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';

interface HomepageContent {
  hero_title?: string;
  hero_highlight?: string;
  hero_subtitle?: string;
  hero_cta_primary?: string;
  hero_cta_secondary?: string;
  stats?: { label: string; value: string }[];
  how_we_help?: { title: string; description: string }[];
}

interface Testimonial {
  id: number;
  quote: string;
  author: string;
}

export default async function HomePage() {
  const config = BRANDING;

  const [user, testimonials] = await Promise.all([
    getOptionalUser(),
    apiFetch<{ data: Testimonial[] }>('/api/v1/testimonials', { skipAuth: true }),
  ]);
  const homepage = (config.branding.homepage ?? {}) as HomepageContent;
  const stats = homepage.stats ?? [];
  const howWeHelp = homepage.how_we_help ?? [];

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated={user !== null} />

      <main className="flex-1">
        {/* Hero */}
        <section className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-20">
          <div>
            <h1 className="text-4xl font-extrabold leading-tight text-neutral-900 sm:text-5xl">
              {homepage.hero_title ?? `Grow with ${config.tenant.name}.`}{' '}
              <span className="text-[var(--brand-accent)]">{homepage.hero_highlight}</span>
            </h1>
            <p className="mt-5 max-w-lg text-lg text-neutral-600">
              {homepage.hero_subtitle ??
                'Practical training, expert coaching, and valuable resources to help you excel.'}
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href="/courses"
                className="rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
              >
                {homepage.hero_cta_primary ?? 'Explore Courses'}
              </Link>
              <Link
                href="/coaching"
                className="rounded-md border border-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-[var(--brand-primary)] hover:bg-neutral-50"
              >
                {homepage.hero_cta_secondary ?? 'Book a Free Consultation'}
              </Link>
            </div>
          </div>

          <div className="relative aspect-4/3 w-full overflow-hidden rounded-2xl bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60">
            {config.branding.hero_image_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={config.branding.hero_image_url}
                alt={`${config.tenant.name} hero`}
                className="absolute inset-0 h-full w-full object-cover"
              />
            ) : (
              <div className="absolute inset-0 grid place-items-center text-white/70">
                <span className="text-sm">{config.tenant.name} hero image</span>
              </div>
            )}
          </div>
        </section>

        {/* Stats bar */}
        {stats.length > 0 && (
          <section className="bg-[var(--brand-primary)] py-8">
            <div className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 text-center text-white sm:px-6 md:grid-cols-4">
              {stats.map((stat) => (
                <div key={stat.label}>
                  <p className="text-2xl font-bold sm:text-3xl">{stat.value}</p>
                  <p className="mt-1 text-xs text-white/80 sm:text-sm">{stat.label}</p>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* How we help */}
        {howWeHelp.length > 0 && (
          <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 className="text-center text-2xl font-bold text-neutral-900">How We Help You Succeed</h2>
            <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
              {howWeHelp.map((item) => (
                <div key={item.title} className="rounded-xl border border-neutral-200 p-6 text-center">
                  <div className="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-full bg-[var(--brand-accent)]/15 text-[var(--brand-accent)]">
                    ✦
                  </div>
                  <h3 className="font-semibold text-neutral-900">{item.title}</h3>
                  <p className="mt-1 text-sm text-neutral-600">{item.description}</p>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* Testimonials */}
        {testimonials.data.length > 0 && (
          <section className="bg-[var(--brand-primary)] py-14">
            <div className="mx-auto flex max-w-6xl justify-center gap-6 overflow-x-auto px-6 pb-1">
              {testimonials.data.map((testimonial) => (
                <blockquote
                  key={testimonial.id}
                  className="w-80 shrink-0 rounded-xl bg-white/10 p-6 text-center text-white"
                >
                  <p className="text-sm italic">&ldquo;{testimonial.quote}&rdquo;</p>
                  <footer className="mt-3 text-xs text-white/70">&mdash; {testimonial.author}</footer>
                </blockquote>
              ))}
            </div>
          </section>
        )}
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
