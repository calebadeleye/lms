import { HOME_CONTENT } from '@/lib/home-content';
import { PhotoSlotImage } from '@/components/home/photo-slot';

export function WhoWeAreSection() {
  const { whoWeAre } = HOME_CONTENT;

  return (
    <section className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2">
      <PhotoSlotImage photo={whoWeAre.photo} className="aspect-4/3 w-full rounded-2xl" />

      <div>
        <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{whoWeAre.eyebrow}</p>
        <h2 className="mt-2 text-2xl font-bold text-[var(--brand-primary)] sm:text-3xl">{whoWeAre.heading}</h2>
        <p className="mt-3 text-sm font-semibold text-neutral-700">{whoWeAre.meaning}</p>
        <div className="mt-4 space-y-4 text-sm text-neutral-600">
          {whoWeAre.paragraphs.map((paragraph, i) => (
            <p key={i}>{paragraph}</p>
          ))}
        </div>
      </div>
    </section>
  );
}
