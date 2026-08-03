'use client';

import { useRef } from 'react';
import { HOME_CONTENT } from '@/lib/home-content';

export function TestimonialCarouselSection() {
  const { memberVoices } = HOME_CONTENT;
  const scroller = useRef<HTMLDivElement>(null);

  function move(direction: number) {
    scroller.current?.scrollBy({ left: direction * Math.min(scroller.current.clientWidth * 0.9, 380), behavior: 'smooth' });
  }

  return (
    <section className="px-5 py-16 sm:px-7 sm:py-20 lg:px-8">
      <div className="mx-auto max-w-[980px]">
        <div className="flex items-end justify-between gap-6">
          <div>
            <p className="text-xs font-extrabold uppercase tracking-[0.07em] text-[#006c70]">{memberVoices.eyebrow}</p>
            <h2 className="mt-2 text-3xl font-extrabold tracking-[-0.035em] text-[#092d32]">Real Stories. Real Impact<span className="text-[#f4b728]">.</span></h2>
          </div>
          <a href="/testimonials" className="hidden text-xs font-extrabold text-[#006c70] sm:inline-flex">Read More Stories →</a>
        </div>

        <div className="relative mt-8">
          <button type="button" onClick={() => move(-1)} aria-label="Previous testimonials" className="absolute -left-5 top-1/2 z-10 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-[#f7f6f0] text-[#536063] shadow-sm sm:grid">←</button>
          <div ref={scroller} className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            {memberVoices.testimonials.map((testimonial) => (
              <article key={testimonial.author} className="min-w-[86%] snap-start rounded-2xl border border-[#e7e4dc] bg-white p-6 sm:min-w-[calc((100%-2rem)/3)]">
                <span className="text-4xl font-extrabold leading-none text-[#006c70]">“</span>
                <p className="mt-1 min-h-[100px] text-[14px] leading-6 text-[#4b5759]">{testimonial.quote}</p>
                <div className="mt-5 flex items-center gap-3 border-t border-[#edf0ef] pt-4">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={testimonial.photo} alt="" className="h-9 w-9 rounded-full object-cover" />
                  <div className="min-w-0">
                    <p className="text-xs font-extrabold text-[#172326]">{testimonial.author}</p>
                    <p className="text-[10px] text-[#6d7779]">{testimonial.role}</p>
                  </div>
                  <span aria-label="5 out of 5 stars" className="ml-auto text-xs tracking-[0.08em] text-[#f4b728]">★★★★★</span>
                </div>
              </article>
            ))}
          </div>
          <button type="button" onClick={() => move(1)} aria-label="Next testimonials" className="absolute -right-5 top-1/2 z-10 hidden h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-[#f7f6f0] text-[#536063] shadow-sm sm:grid">→</button>
        </div>
      </div>
    </section>
  );
}
