import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { BrandIcon } from '@/components/home/brand-icon';
import { AnimatedCounter } from '@/components/home/animated-counter';

export function HeroSection() {
  const { hero, impact } = HOME_CONTENT;

  return (
    <section className="relative overflow-hidden bg-[radial-gradient(circle_at_58%_20%,rgba(244,183,40,0.08),transparent_26%),linear-gradient(180deg,#fff_0%,#fff_88%,#f7f6f0_100%)]">
      <div className="mx-auto grid max-w-[1240px] items-center gap-10 px-5 pb-14 pt-12 sm:px-7 sm:pt-16 lg:min-h-[610px] lg:grid-cols-[44%_56%] lg:gap-0 lg:px-8 lg:pb-24 lg:pt-10">
        <div className="home-rise relative z-20 max-w-[560px]">
          <p className="text-[13px] font-extrabold uppercase tracking-[0.08em] text-[#006c70] sm:text-sm">{hero.eyebrow}</p>
          <h1 className="mt-4 text-[42px] font-extrabold leading-[1.03] tracking-[-0.045em] text-[#092d32] sm:text-[58px] lg:text-[62px]">
            Discover Who<br />You Are<span className="text-[#f4b728]">.</span><br />
            <span className="text-[#006c70]">Build What<br />Comes Next</span><span className="text-[#f4b728]">.</span>
          </h1>
          <p className="mt-5 max-w-[530px] text-base leading-7 text-[#485456] sm:text-[17px]">{hero.description}</p>

          <div className="mt-7 flex flex-col gap-3 sm:flex-row">
            <Link href={hero.ctaPrimary.href} className="group inline-flex min-h-12 items-center justify-center gap-5 rounded-lg bg-[#006c70] px-6 text-[15px] font-bold text-white transition hover:-translate-y-0.5 hover:bg-[#075d61]">
              {hero.ctaPrimary.label}<span aria-hidden className="transition-transform group-hover:translate-x-1">→</span>
            </Link>
            <Link href={hero.ctaSecondary.href} className="inline-flex min-h-12 items-center justify-center gap-4 rounded-lg border border-[#006c70] bg-white px-6 text-[15px] font-bold text-[#073d40] transition hover:bg-[#006c70]/5">
              {hero.ctaSecondary.label}<BrandIcon name="people" className="h-5 w-5" />
            </Link>
          </div>

          <div className="mt-7 grid grid-cols-[120px_1fr] items-center gap-3">
            <div className="flex w-[120px] -space-x-2" aria-hidden>
              {['/marketing/community-2.jpg', '/marketing/community-5.jpg', '/marketing/community-6.jpg', '/marketing/hero.jpg'].map((src) => (
                // eslint-disable-next-line @next/next/no-img-element
                <img key={src} src={src} alt="" className="h-9 w-9 rounded-full border-2 border-white object-cover" />
              ))}
            </div>
            <p className="min-w-0 max-w-[250px] text-[13px] font-semibold leading-5 text-[#3f4c4e]">{hero.quote}</p>
          </div>
        </div>

        <div className="hero-media-curve relative aspect-[1000/650] sm:aspect-auto sm:min-h-[570px] lg:-mt-10 lg:min-h-[650px]">
          <svg
            viewBox="0 0 1000 650"
            preserveAspectRatio="xMidYMid slice"
            className="absolute inset-0 h-full w-full overflow-visible"
            role="img"
            aria-label={hero.photo.alt}
          >
            <defs>
              <clipPath id="hero-photo-curve">
                <path d="M180 0H930L1000 70V360C970 395 930 455 840 500C720 560 570 570 420 535C290 505 210 445 0 425C90 380 110 330 90 270L180 0Z" />
              </clipPath>
              <linearGradient id="hero-photo-fade" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stopColor="white" stopOpacity="1" />
                <stop offset="0.35" stopColor="white" stopOpacity="0.72" />
                <stop offset="1" stopColor="white" stopOpacity="0" />
              </linearGradient>
              <linearGradient id="hero-teal-sweep" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stopColor="#0b7c80" />
                <stop offset="1" stopColor="#005b60" />
              </linearGradient>
            </defs>

            <path
              d="M1000 360C970 395 930 455 840 500C720 560 570 570 420 535C290 505 210 445 0 425V650H1000V360Z"
              fill="url(#hero-teal-sweep)"
            />
            {hero.photo.src && (
              <image
                href={hero.photo.src}
                width="1000"
                height="650"
                preserveAspectRatio="xMidYMid slice"
                clipPath="url(#hero-photo-curve)"
              />
            )}
            <rect width="390" height="650" fill="url(#hero-photo-fade)" clipPath="url(#hero-photo-curve)" />
            <path
              d="M1000 360C970 395 930 455 840 500C720 560 570 570 420 535C290 505 210 445 0 425"
              fill="none"
              stroke="#f4b728"
              strokeWidth="3"
              vectorEffect="non-scaling-stroke"
            />
            <path d="M180 0L135 68" fill="none" stroke="#f4b728" strokeWidth="3" vectorEffect="non-scaling-stroke" />
            <path d="M930 0L1000 70" fill="none" stroke="#f4b728" strokeWidth="3" vectorEffect="non-scaling-stroke" />
          </svg>

          <div className="absolute left-0 top-[49%] z-10 hidden w-[235px] -translate-y-1/2 space-y-4 sm:block lg:-left-5">
            {hero.featureCards.map((card) => (
              <div key={card.title} className="flex items-start gap-3 rounded-xl border border-white/80 bg-white/95 p-4 shadow-[0_10px_25px_rgba(7,61,64,0.11)] backdrop-bl">
                <span className="mt-0.5 text-[#006c70]"><BrandIcon name={card.icon} className="h-6 w-6" /></span>
                <span>
                  <strong className="block text-[13px] font-extrabold text-[#172326]">{card.title}</strong>
                  <span className="mt-1 block text-[11px] leading-[1.55] text-[#5b6668]">{card.description}</span>
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-3 sm:hidden">
          {hero.featureCards.map((card) => (
            <div key={card.title} className="rounded-xl border border-[#e7ecea] bg-white p-4 shadow-[0_8px_22px_rgba(7,61,64,0.06)]">
              <BrandIcon name={card.icon} className="h-6 w-6 text-[#006c70]" />
              <h2 className="mt-3 text-sm font-extrabold text-[#172326]">{card.title}</h2>
              <p className="mt-1 text-xs leading-5 text-[#5b6668]">{card.description}</p>
            </div>
          ))}
        </div>
      </div>

      <div className="relative z-30 mx-auto -mt-4 max-w-[1240px] px-5 pb-12 sm:px-7 lg:-mt-14 lg:px-8">
        <div className="grid grid-cols-2 rounded-[20px] bg-[#064c50] px-3 py-5 text-white shadow-[0_18px_38px_rgba(7,61,64,0.14)] sm:px-6 lg:grid-cols-4 lg:py-7">
          {impact.map((item, index) => (
            <div key={item.label} className={`flex items-center gap-3 px-2 py-4 sm:justify-center lg:px-5 lg:py-0 ${index % 2 ? 'border-l border-white/20' : ''} ${index > 1 ? 'border-t border-white/15 lg:border-t-0' : ''} ${index > 0 ? 'lg:border-l lg:border-white/25' : ''}`}>
              <span className="text-[#f4b728]"><BrandIcon name={item.icon} className="h-7 w-7 lg:h-9 lg:w-9" /></span>
              <span>
                <strong className="block text-xl font-extrabold leading-none sm:text-2xl">
                  <AnimatedCounter value={item.value} />
                </strong>
                <span className="mt-1.5 block text-[11px] leading-4 text-white/90 sm:text-xs">{item.label}</span>
              </span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
