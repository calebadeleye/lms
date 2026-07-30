import type { PhotoSlot } from '@/lib/home-content';

/** The site's one photo-placeholder convention (same gradient used for
 * `hero_image_url`/course thumbnails) — renders the real image once `src`
 * is supplied, otherwise a teal-to-gold gradient box with the alt text. */
export function PhotoSlotImage({ photo, className }: { photo: PhotoSlot; className?: string }) {
  return (
    <div className={`relative overflow-hidden bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60 ${className ?? ''}`}>
      {photo.src ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img src={photo.src} alt={photo.alt} className="absolute inset-0 h-full w-full object-cover" />
      ) : (
        <div className="absolute inset-0 grid place-items-center p-2 text-center text-xs text-white/70">
          <span>{photo.alt}</span>
        </div>
      )}
    </div>
  );
}
