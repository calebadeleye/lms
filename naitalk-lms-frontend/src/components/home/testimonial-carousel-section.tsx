'use client';

import Link from 'next/link';
import { useState } from 'react';
import { HOME_CONTENT } from '@/lib/home-content';
import { PhotoSlotImage } from '@/components/home/photo-slot';

export function TestimonialCarouselSection() {
  const { moreStories } = HOME_CONTENT;
  const [active, setActive] = useState(0);

  return (
    <section className="bg-[var(--brand-primary)] py-14">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <p className="text-center text-lg italic text-white/90">
          <span aria-hidden className="mr-1 text-2xl text-[var(--brand-accent)]">
            &ldquo;
          </span>
          {moreStories.heading}
        </p>

        <div className="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr] lg:items-center">
          <div className="grid gap-4 sm:grid-cols-3">
            {moreStories.testimonials.map((testimonial, i) => (
              <button
                key={testimonial.author}
                onClick={() => setActive(i)}
                className={`rounded-xl p-5 text-left transition ${
                  active === i ? 'bg-white/15 ring-1 ring-white/40' : 'bg-white/5 hover:bg-white/10'
                }`}
              >
                <p className="text-xs italic text-white/90">&ldquo;{testimonial.quote}&rdquo;</p>
                <p className="mt-3 text-xs font-semibold text-[var(--brand-accent)]">&mdash; {testimonial.author}</p>
              </button>
            ))}
          </div>

          <PhotoSlotImage photo={moreStories.photo} className="aspect-square w-full rounded-2xl" />
        </div>

        <div className="mt-6 flex items-center justify-center gap-2" role="tablist" aria-label="Testimonial pagination">
          {moreStories.testimonials.map((testimonial, i) => (
            <button
              key={testimonial.author}
              role="tab"
              aria-selected={active === i}
              aria-label={`Show testimonial from ${testimonial.author}`}
              onClick={() => setActive(i)}
              className={`h-2 rounded-full transition-all ${active === i ? 'w-6 bg-[var(--brand-accent)]' : 'w-2 bg-white/30 hover:bg-white/50'}`}
            />
          ))}
        </div>

        <div className="mt-6 text-center">
          <Link
            href={moreStories.moreLink.href}
            className="inline-block rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-neutral-900 hover:opacity-90"
          >
            {moreStories.moreLink.label} &rarr;
          </Link>
        </div>
      </div>
    </section>
  );
}
