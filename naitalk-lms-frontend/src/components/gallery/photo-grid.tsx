'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import type { GalleryPhoto } from '@/lib/gallery-content';

/**
 * Responsive photo grid; clicking a photo opens a full-screen viewer with
 * previous/next (buttons, arrow keys, or swipe) and Esc / backdrop to close.
 */
export function PhotoGrid({ photos }: { photos: GalleryPhoto[] }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  return (
    <>
      <ul className="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3">
        {photos.map((photo, index) => (
          <li key={photo.src}>
            <button
              type="button"
              onClick={() => setOpenIndex(index)}
              className="group block w-full overflow-hidden rounded-xl bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand-primary)]"
              aria-label={`View photo: ${photo.caption}`}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={photo.thumb}
                alt={photo.alt}
                loading="lazy"
                className="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-[1.04]"
              />
            </button>
            <p className="mt-1.5 px-0.5 text-xs font-medium text-neutral-600">{photo.caption}</p>
          </li>
        ))}
      </ul>

      {openIndex !== null && (
        <PhotoViewer photos={photos} index={openIndex} onIndexChange={setOpenIndex} onClose={() => setOpenIndex(null)} />
      )}
    </>
  );
}

function PhotoViewer({
  photos,
  index,
  onIndexChange,
  onClose,
}: {
  photos: GalleryPhoto[];
  index: number;
  onIndexChange: (index: number) => void;
  onClose: () => void;
}) {
  const photo = photos[index];
  const touchStartX = useRef<number | null>(null);
  const closeButton = useRef<HTMLButtonElement>(null);

  const go = useCallback(
    (step: number) => onIndexChange((index + step + photos.length) % photos.length),
    [index, photos.length, onIndexChange]
  );

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowRight') go(1);
      if (e.key === 'ArrowLeft') go(-1);
    }
    window.addEventListener('keydown', onKey);
    // Stop the page behind the viewer from scrolling while it's open.
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = previousOverflow;
    };
  }, [go, onClose]);

  useEffect(() => {
    closeButton.current?.focus();
  }, []);

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={photo.caption}
      className="fixed inset-0 z-[100] flex flex-col bg-black/90"
      onClick={onClose}
      onTouchStart={(e) => (touchStartX.current = e.touches[0].clientX)}
      onTouchEnd={(e) => {
        if (touchStartX.current === null) return;
        const dx = e.changedTouches[0].clientX - touchStartX.current;
        if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
        touchStartX.current = null;
      }}
    >
      <div className="flex items-center justify-between px-4 py-3 text-sm text-white/80" onClick={(e) => e.stopPropagation()}>
        <span>
          {index + 1} / {photos.length}
        </span>
        <button
          ref={closeButton}
          type="button"
          onClick={onClose}
          className="grid h-10 w-10 place-items-center rounded-full text-2xl leading-none text-white hover:bg-white/10"
          aria-label="Close"
        >
          ×
        </button>
      </div>

      <div className="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-16">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          key={photo.src}
          src={photo.src}
          alt={photo.alt}
          className="max-h-full max-w-full rounded-lg object-contain"
          onClick={(e) => e.stopPropagation()}
        />

        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            go(-1);
          }}
          className="absolute left-2 top-1/2 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 sm:grid"
          aria-label="Previous photo"
        >
          ‹
        </button>
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            go(1);
          }}
          className="absolute right-2 top-1/2 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 sm:grid"
          aria-label="Next photo"
        >
          ›
        </button>
      </div>

      <p className="px-4 py-4 text-center text-sm font-medium text-white" onClick={(e) => e.stopPropagation()}>
        {photo.caption}
      </p>
    </div>
  );
}
