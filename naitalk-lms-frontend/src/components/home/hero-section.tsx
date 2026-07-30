import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { PhotoSlotImage } from '@/components/home/photo-slot';
import { HERO_AUDIENCE_ICONS, JoinIcon } from '@/components/home/home-icons';

export function HeroSection() {
  const { hero } = HOME_CONTENT;

  return (
    <section className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center lg:py-20">
      <div>
        <h1 className="text-4xl font-extrabold leading-tight text-[var(--brand-primary)] sm:text-5xl">{hero.heading}</h1>
        <p className="mt-4 text-lg font-medium text-neutral-700">{hero.subheading}</p>
        <p className="mt-4 max-w-lg text-sm text-neutral-600">{hero.description}</p>

        <div className="mt-8 flex flex-wrap gap-3">
          <Link
            href={hero.ctaPrimary.href}
            className="rounded-md bg-[var(--brand-primary)] px-6 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
          >
            {hero.ctaPrimary.label} &rarr;
          </Link>
          <Link
            href={hero.ctaSecondary.href}
            className="inline-flex items-center gap-2 rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-neutral-900 shadow-sm hover:opacity-90"
          >
            <JoinIcon className="h-4 w-4" />
            {hero.ctaSecondary.label} &rarr;
          </Link>
        </div>

        <ul className="mt-8 flex flex-wrap gap-6 text-xs font-medium text-neutral-600">
          {hero.audiences.map((audience) => {
            const Icon = HERO_AUDIENCE_ICONS[audience.icon];
            return (
              <li key={audience.label} className="flex items-center gap-2">
                <span aria-hidden className="grid h-6 w-6 place-items-center rounded-full bg-[var(--brand-accent)]/15 text-[var(--brand-primary)]">
                  <Icon className="h-3.5 w-3.5" />
                </span>
                {audience.label}
              </li>
            );
          })}
        </ul>
      </div>

      <div className="relative aspect-4/3 w-full overflow-hidden rounded-2xl shadow-lg">
        <PhotoSlotImage photo={hero.photo} className="h-full w-full" />

        <div
          className={[
            // Mobile: a full-width bottom caption band (vertical fade) so the
            // quote has real room to breathe. sm+: reverts to a diagonal
            // wedge in the corner, closer to the original design — there's
            // enough photo width by then for a narrower panel to still read
            // comfortably.
            'absolute inset-x-0 bottom-0 flex h-2/5 items-end bg-gradient-to-t from-[var(--brand-primary)] to-transparent p-6 text-white',
            'sm:inset-x-auto sm:right-0 sm:h-full sm:w-3/5 sm:bg-gradient-to-bl sm:from-[var(--brand-primary)] sm:from-45% sm:to-transparent',
            'md:w-1/2',
          ].join(' ')}
        >
          <p className="text-sm italic leading-relaxed">
            <span aria-hidden className="mr-1 text-2xl leading-none text-[var(--brand-accent)]">
              &ldquo;
            </span>
            {hero.quote}
          </p>
        </div>
      </div>
    </section>
  );
}
