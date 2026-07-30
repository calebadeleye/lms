import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';

interface Testimonial {
  id: number;
  quote: string;
  author: string;
}

/** The real destination for the homepage's "Read more stories"/"View More
 * Testimonials" CTAs — lists every testimonial an admin has added via
 * /admin/testimonials (GET /api/v1/testimonials is public, unauthenticated). */
export default async function TestimonialsPage() {
  const config = BRANDING;
  const [user, testimonials] = await Promise.all([
    getOptionalUser(),
    apiFetch<{ data: Testimonial[] }>('/api/v1/testimonials', { skipAuth: true }),
  ]);

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated={user !== null} />

      <main className="flex-1">
        <section className="mx-auto max-w-4xl px-4 py-14 text-center sm:px-6">
          <h1 className="text-3xl font-bold text-[var(--brand-primary)] sm:text-4xl">Member Stories</h1>
          <p className="mt-3 text-sm text-neutral-600">What our community has to say about their journey with {config.tenant.name}.</p>
        </section>

        <section className="mx-auto max-w-6xl px-4 pb-14 sm:px-6">
          {testimonials.data.length === 0 ? (
            <p className="text-center text-sm text-neutral-500">No testimonials yet — check back soon.</p>
          ) : (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {testimonials.data.map((testimonial) => (
                <blockquote key={testimonial.id} className="rounded-xl border border-neutral-200 bg-white p-6">
                  <p className="text-sm italic text-neutral-700">&ldquo;{testimonial.quote}&rdquo;</p>
                  <footer className="mt-3 text-xs font-semibold text-[var(--brand-primary)]">&mdash; {testimonial.author}</footer>
                </blockquote>
              ))}
            </div>
          )}
        </section>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
