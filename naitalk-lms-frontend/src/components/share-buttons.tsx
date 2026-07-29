'use client';

import { useState } from 'react';
import { FacebookIcon, XIcon, InstagramIcon } from '@/components/social-icons';

/**
 * Deliberately only Facebook, X, and Instagram — no generic "copy link" /
 * email / WhatsApp grab-bag. `url` must always be one of this app's own
 * pages (a course or certificate page), never a raw lesson video URL —
 * callers are responsible for that; this component doesn't know or care
 * what's embedded inside the page it's sharing.
 */
export function ShareButtons({ url, title }: { url: string; title: string }) {
  const [copied, setCopied] = useState(false);

  function openPopup(shareUrl: string) {
    window.open(shareUrl, '_blank', 'noopener,noreferrer,width=600,height=500');
  }

  function shareFacebook() {
    openPopup(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`);
  }

  function shareX() {
    openPopup(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`);
  }

  // Instagram has no web share-intent URL — posting only happens from
  // their app. Copying the link is the standard workaround: paste it into
  // a Story, bio, or DM.
  async function shareInstagram() {
    await navigator.clipboard.writeText(url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  }

  return (
    <div className="flex items-center gap-2">
      <button
        onClick={shareFacebook}
        aria-label="Share on Facebook"
        title="Share on Facebook"
        className="grid h-9 w-9 place-items-center rounded-full border border-neutral-200 text-neutral-600 hover:border-[var(--tenant-primary)] hover:text-[var(--tenant-primary)]"
      >
        <FacebookIcon className="h-4 w-4" />
      </button>
      <button
        onClick={shareX}
        aria-label="Share on X"
        title="Share on X"
        className="grid h-9 w-9 place-items-center rounded-full border border-neutral-200 text-neutral-600 hover:border-[var(--tenant-primary)] hover:text-[var(--tenant-primary)]"
      >
        <XIcon className="h-4 w-4" />
      </button>
      <div className="relative">
        <button
          onClick={shareInstagram}
          aria-label="Copy link to share on Instagram"
          title="Copy link to share on Instagram"
          className="grid h-9 w-9 place-items-center rounded-full border border-neutral-200 text-neutral-600 hover:border-[var(--tenant-primary)] hover:text-[var(--tenant-primary)]"
        >
          <InstagramIcon className="h-4 w-4" />
        </button>
        {copied && (
          <span className="absolute left-1/2 top-full mt-1.5 w-max -translate-x-1/2 rounded-md bg-neutral-900 px-2 py-1 text-[11px] text-white">
            Link copied — paste it into Instagram
          </span>
        )}
      </div>
    </div>
  );
}
