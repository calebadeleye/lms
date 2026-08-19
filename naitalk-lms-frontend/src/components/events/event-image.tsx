'use client';

import { useState } from 'react';

/**
 * Google Drive's thumbnail endpoint (see events-content.ts) isn't an
 * official, guaranteed-reliable hotlinking API — it occasionally fails to
 * serve a specific file as a cross-origin <img> even though the same file
 * loads fine navigated to directly. Falls back to a plain placeholder
 * rather than showing a broken-image icon when that happens.
 */
export function EventImage({ src, alt, className }: { src: string; alt: string; className?: string }) {
  const [failed, setFailed] = useState(false);

  if (failed) {
    return (
      <div className={`grid place-items-center bg-[var(--brand-primary)]/10 text-xs text-neutral-400 ${className ?? ''}`}>
        Photo unavailable
      </div>
    );
  }

  // eslint-disable-next-line @next/next/no-img-element
  return <img src={src} alt={alt} className={className} loading="lazy" onError={() => setFailed(true)} />;
}
