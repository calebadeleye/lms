import { HOME_CONTENT } from '@/lib/home-content';

export function TestimonialCarouselSection() {
  const { moreStories } = HOME_CONTENT;

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
        <h2
          className="text-[22px] font-black leading-tight text-white"
          style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
        >
          <span aria-hidden className="mr-3 align-middle text-4xl leading-none text-white/35">
            &ldquo;
          </span>
          {moreStories.heading}
        </h2>

        <div className="mt-8 columns-1 gap-4 sm:columns-2 lg:columns-3">
          {moreStories.testimonials.map((quote, i) => (
            <p
              key={i}
              className="mb-4 break-inside-avoid rounded-md border border-white/25 bg-white/5 p-4 text-[11px] font-semibold leading-relaxed text-white/95"
            >
              &ldquo;{quote}&rdquo;
            </p>
          ))}
        </div>
      </div>
    </section>
  );
}
