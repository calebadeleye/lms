import { HOME_CONTENT } from '@/lib/home-content';
import { PhotoSlotImage } from '@/components/home/photo-slot';

export function CommunityGallerySection() {
  const { communityExperience } = HOME_CONTENT;

  return (
    <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
      <div className="text-center">
        <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{communityExperience.eyebrow}</p>
        <h2 className="mt-2 text-2xl font-bold text-[var(--brand-primary)] sm:text-3xl">{communityExperience.heading}</h2>
      </div>

      <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {communityExperience.items.map((item) => (
          <div key={item.caption}>
            <PhotoSlotImage photo={item.photo} className="aspect-video w-full rounded-lg" />
            <p className="mt-2 text-center text-sm font-semibold text-neutral-700">{item.caption}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
