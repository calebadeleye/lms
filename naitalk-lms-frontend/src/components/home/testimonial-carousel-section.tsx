'use client';

import { useEffect, useState } from 'react';
import { HOME_CONTENT } from '@/lib/home-content';

const PER_PAGE = 3;
const AUTO_ADVANCE_MS = 6000;

export function TestimonialCarouselSection() {
  const { moreStories } = HOME_CONTENT;
  const pageCount = Math.ceil(moreStories.testimonials.length / PER_PAGE);
  const [page, setPage] = useState(0);

  const current = moreStories.testimonials.slice(page * PER_PAGE, page * PER_PAGE + PER_PAGE);

  function go(delta: number) {
    setPage((p) => (p + delta + pageCount) % pageCount);
  }

  useEffect(() => {
    const timer = setInterval(() => go(1), AUTO_ADVANCE_MS);
    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pageCount]);

  return (
    <section className="relative isolate overflow-hidden bg-[#005f61] text-white">
      <div className="absolute inset-y-0 right-0 z-0 hidden w-[44%] md:block">
        {moreStories.photo.src ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={moreStories.photo.src} alt="" className="h-full w-full object-cover object-center opacity-70" />
        ) : null}
        <div className="absolute inset-0 bg-linear-to-r from-[#005f61] via-[#005f61]/65 to-transparent" />
      </div>
      <div className="absolute inset-0 z-0 bg-linear-to-r from-[#005f61] via-[#007173]/92 to-[#005f61]/25" />

      <div className="relative z-10 mx-auto max-w-[1120px] px-4 py-16 sm:px-8 sm:py-20">
        <div className="flex items-center justify-between gap-4">
          <h2
            className="text-[22px] font-black leading-tight text-white"
            style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
          >
            <span aria-hidden className="mr-3 align-middle text-4xl leading-none text-white/35">
              &ldquo;
            </span>
            {moreStories.heading}
          </h2>

          <div className="hidden shrink-0 items-center gap-2 sm:flex">
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Previous testimonials"
              className="grid h-9 w-9 place-items-center rounded-full border border-white/25 text-white hover:bg-white/10"
            >
              &larr;
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Next testimonials"
              className="grid h-9 w-9 place-items-center rounded-full border border-white/25 text-white hover:bg-white/10"
            >
              &rarr;
            </button>
          </div>
        </div>

        <div className="mt-8 grid gap-4 sm:grid-cols-3">
          {current.map((quote, i) => (
            <p
              key={page * PER_PAGE + i}
              className="min-h-[140px] rounded-md border border-white/25 bg-white/5 p-5 text-base font-medium leading-relaxed text-white"
            >
              &ldquo;{quote}&rdquo;
            </p>
          ))}
        </div>

        <div className="mt-6 flex items-center justify-center gap-4 sm:justify-start">
          <div className="flex items-center gap-2 sm:hidden">
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Previous testimonials"
              className="grid h-9 w-9 place-items-center rounded-full border border-white/25 text-white hover:bg-white/10"
            >
              &larr;
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Next testimonials"
              className="grid h-9 w-9 place-items-center rounded-full border border-white/25 text-white hover:bg-white/10"
            >
              &rarr;
            </button>
          </div>

          <div className="flex items-center gap-2" role="tablist" aria-label="Testimonial pagination">
            {Array.from({ length: pageCount }).map((_, i) => (
              <button
                key={i}
                role="tab"
                aria-selected={page === i}
                aria-label={`Show testimonials ${i + 1} of ${pageCount}`}
                onClick={() => setPage(i)}
                className={`h-2 w-2 rounded-full transition-all ${page === i ? 'bg-[#ffbd11]' : 'bg-white/55 hover:bg-white/80'}`}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
