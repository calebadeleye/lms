import { HOME_CONTENT } from '@/lib/home-content';
import { PhotoSlotImage } from '@/components/home/photo-slot';

export function WhoWeAreSection() {
  const { whoWeAre } = HOME_CONTENT;

  return (
    <section className="mx-auto grid max-w-[1120px] items-center gap-8 px-4 py-16 sm:px-8 sm:py-20 md:grid-cols-[40%_37%_18%]">
      <PhotoSlotImage photo={whoWeAre.photo} className="aspect-[1.65/1] w-full rounded-lg shadow-[0_12px_28px_rgba(15,23,42,0.12)]" />

      <div className="self-start md:pt-1">
        <p className="text-[11px] font-black uppercase text-[var(--brand-primary)]">{whoWeAre.eyebrow}</p>
        <h2
          className="mt-1 max-w-[420px] text-[30px] font-black leading-[0.98] text-[#082f35]"
          style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
        >
          {whoWeAre.heading}
        </h2>
        <div className="mt-3 h-1 w-10 bg-[#ffbd11]" />
        <p className="mt-3 text-[12px] font-semibold leading-relaxed text-neutral-800">
          HR GEMs means <span className="text-[var(--brand-primary)]">Great.Excellent.Minds.</span>
        </p>
        <div className="mt-2 space-y-3 text-[12px] font-medium leading-relaxed text-neutral-900">
          {whoWeAre.paragraphs.map((paragraph, i) => (
            <p key={i}>{paragraph}</p>
          ))}
        </div>
      </div>

      <div className="hidden self-stretch md:block">
        <GrowthIllustration />
      </div>
    </section>
  );
}

function GrowthIllustration() {
  return (
    <div className="relative h-full min-h-[220px] text-[var(--brand-primary)]">
      <div className="absolute left-0 top-8 grid grid-cols-7 gap-1 opacity-25" aria-hidden>
        {Array.from({ length: 28 }).map((_, index) => (
          <span key={index} className="h-1 w-1 rounded-full bg-[#ffbd11]" />
        ))}
      </div>
      <span aria-hidden className="absolute right-16 top-16 h-10 w-10 rounded-full bg-[#ffcf42]" />
      <svg viewBox="0 0 230 220" fill="none" className="absolute inset-x-0 bottom-0 h-full w-full" aria-hidden="true">
        <path d="M32 178C64 151 84 124 113 117C145 109 169 91 197 58" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        <path d="M72 172C103 171 131 163 157 146" stroke="currentColor" strokeWidth="2" strokeDasharray="4 6" strokeLinecap="round" />
        <path d="M162 33h40v25h-40z" stroke="currentColor" strokeWidth="3" />
        <path d="m202 33-16 8 16 8" stroke="#ffcf42" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
        <path d="M162 33v110" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        <circle cx="60" cy="141" r="7" stroke="currentColor" strokeWidth="3" />
        <path d="M60 148v26M49 160h22M45 174h30" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        <circle cx="105" cy="109" r="8" stroke="currentColor" strokeWidth="3" />
        <path d="M105 117v34M91 132h28M85 151h40" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        <circle cx="153" cy="74" r="9" stroke="currentColor" strokeWidth="3" />
        <path d="M153 83v50M136 101h34M128 133h54" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
      </svg>
    </div>
  );
}
