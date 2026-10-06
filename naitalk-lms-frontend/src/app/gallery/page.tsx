import type { Metadata } from 'next';
import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { PhotoGrid } from '@/components/gallery/photo-grid';
import { GALLERY_ALBUMS } from '@/lib/gallery-content';

export const metadata: Metadata = {
  title: 'Gallery',
  description: 'Photos from HR GEMs Coach Network gatherings — our community learning, connecting and growing together.',
};

export default async function GalleryPage() {
  const config = BRANDING;
  const user = await getOptionalUser();

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-12 sm:px-6">
        <div className="text-center">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">Gallery</p>
          <h1 className="mt-2 text-2xl font-bold text-neutral-900 sm:text-3xl">Moments from Our Community</h1>
          <p className="mx-auto mt-2 max-w-xl text-sm text-neutral-600">
            Learning, connecting and growing together. Tap any photo to see it full size.
          </p>
        </div>

        <div className="mt-10 space-y-14">
          {GALLERY_ALBUMS.map((album) => (
            <section key={album.slug} id={album.slug} aria-labelledby={`${album.slug}-title`}>
              <div className="mb-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{album.year}</p>
                <h2 id={`${album.slug}-title`} className="mt-1 text-lg font-bold text-neutral-900 sm:text-xl">
                  {album.title}
                </h2>
                <p className="mt-1 text-sm text-neutral-600">{album.description}</p>
              </div>
              <PhotoGrid photos={album.photos} />
            </section>
          ))}
        </div>

        <p className="mt-12 text-center text-sm text-neutral-600">
          Want to be in the next photos?{' '}
          <Link href="/events" className="font-semibold text-[var(--brand-primary)] underline">
            See our events
          </Link>
          .
        </p>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
