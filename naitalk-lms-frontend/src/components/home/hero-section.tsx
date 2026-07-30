import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { HERO_AUDIENCE_ICONS, JoinIcon } from '@/components/home/home-icons';

export function HeroSection() {
  const { hero } = HOME_CONTENT;
  const quoteLines = hero.quote.split('. ').map((line, index, lines) => `${line}${index < lines.length - 1 ? '.' : ''}`);

  return (
    <section className="relative isolate overflow-hidden border-b border-neutral-200 bg-white">
      <div className="absolute inset-y-0 right-0 hidden w-[62.5%] md:block">
        {hero.photo.src ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={hero.photo.src} alt={hero.photo.alt} className="h-[76%] w-full object-cover object-center" />
        ) : null}
        <div
          className="absolute inset-y-0 left-0 w-32 md:w-36 lg:w-40"
          style={{
            background:
              'linear-gradient(to right, rgba(255,255,255,1) 0%, rgba(255,255,255,0.75) 30%, rgba(255,255,255,0.25) 65%, rgba(255,255,255,0) 100%)',
          }}
        />
      </div>

      <div
        aria-hidden
        className="absolute bottom-0 right-0 hidden h-[43%] w-[63%] bg-[var(--brand-primary)] md:block"
        style={{ clipPath: 'polygon(17% 78%, 100% 0, 100% 100%, 0 100%)' }}
      />

      <div className="relative mx-auto grid max-w-[1120px] gap-8 px-4 py-10 sm:px-8 md:min-h-[340px] md:grid-cols-[41%_59%] md:px-12 md:py-9">
        <div className="max-w-[365px] self-start">
          <h1
            className="text-[42px] font-black leading-[0.95] text-[#082f35] sm:text-[54px]"
            style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
          >
            <span className="block">Find Your</span>
            <span className="block text-[var(--brand-primary)]">Career Fit</span>
          </h1>
          <p className="mt-3 text-[15px] font-semibold leading-snug text-neutral-800">{hero.subheading}</p>
          <p className="mt-3 max-w-[340px] text-[12.5px] font-medium leading-relaxed text-neutral-900">{hero.description}</p>

          <div className="mt-5 flex flex-wrap gap-3">
            <Link
              href={hero.ctaPrimary.href}
              className="inline-flex items-center rounded-md bg-[var(--brand-primary)] px-5 py-3 text-[13px] font-bold text-white shadow-sm hover:opacity-90"
            >
              {hero.ctaPrimary.label} &rarr;
            </Link>
            <Link
              href={hero.ctaSecondary.href}
              className="inline-flex items-center gap-2 rounded-md bg-[#ffbd11] px-5 py-3 text-[13px] font-bold text-neutral-950 shadow-sm hover:opacity-90"
            >
              <JoinIcon className="h-4 w-4" />
              {hero.ctaSecondary.label} &rarr;
            </Link>
          </div>

          <ul className="mt-6 grid grid-cols-3 gap-4 text-[10px] font-bold leading-tight text-neutral-800">
            {hero.audiences.map((audience) => {
              const Icon = HERO_AUDIENCE_ICONS[audience.icon];
              return (
                <li key={audience.label} className="flex items-center gap-2">
                  <span aria-hidden className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#dff2ee] text-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]/15">
                    <Icon className="h-5 w-5" />
                  </span>
                  {audience.label}
                </li>
              );
            })}
          </ul>
        </div>

        <div className="relative min-h-[235px] overflow-hidden rounded-none md:min-h-0">
          <div className="absolute inset-0 md:hidden">
            {hero.photo.src ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={hero.photo.src} alt={hero.photo.alt} className="h-full w-full object-cover object-center" />
            ) : null}
            <div
              className="absolute inset-y-0 left-0 w-24"
              style={{
                background:
                  'linear-gradient(to right, rgba(255,255,255,1) 0%, rgba(255,255,255,0.7) 35%, rgba(255,255,255,0.2) 70%, rgba(255,255,255,0) 100%)',
              }}
            />
          </div>
          <div
            className="absolute bottom-0 right-0 flex h-32 w-full items-end justify-end bg-[var(--brand-primary)] px-6 py-7 text-white md:h-[43%] md:w-[106%] md:px-9"
            style={{ clipPath: 'polygon(17% 78%, 100% 0, 100% 100%, 0 100%)' }}
          >
            <p className="relative max-w-[205px] pr-8 text-[11px] font-bold leading-relaxed">
              <span aria-hidden className="absolute -left-8 -top-1 text-4xl leading-none text-white/35">
                &ldquo;
              </span>
              {quoteLines.map((line) => (
                <span key={line} className="block">
                  {line}
                </span>
              ))}
              <span aria-hidden className="absolute -right-1 bottom-0 text-4xl leading-none text-white/35">
                &rdquo;
              </span>
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
