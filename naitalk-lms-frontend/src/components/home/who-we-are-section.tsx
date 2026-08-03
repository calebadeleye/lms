import { HOME_CONTENT } from '@/lib/home-content';
import { BrandIcon } from '@/components/home/brand-icon';

const valueColours = {
  teal: 'text-[#006c70] bg-[#e7f5f3]',
  red: 'text-[#ee564f] bg-[#fff0ee]',
  gold: 'text-[#edae18] bg-[#fff7df]',
  green: 'text-[#36a875] bg-[#eaf8f0]',
} as const;

export function WhoWeAreSection() {
  const { whoWeAre } = HOME_CONTENT;

  return (
    <section id="about" className="px-5 py-16 sm:px-7 sm:py-20 lg:px-8">
      <div className="mx-auto grid max-w-[1060px] items-center gap-10 lg:grid-cols-[1.08fr_1fr] lg:gap-14">
        <div className="relative pb-8">
          <div className="h-[390px] overflow-hidden rounded-2xl sm:h-[430px]">
            {whoWeAre.photo.src && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={whoWeAre.photo.src} alt={whoWeAre.photo.alt} className="h-full w-full object-cover" />
            )}
          </div>
          <div className="absolute bottom-0 left-0 max-w-[260px] rounded-2xl bg-[#f7f6f0]/95 p-5 shadow-[0_12px_28px_rgba(7,61,64,0.08)] sm:-left-5">
            <span className="text-3xl font-extrabold leading-none text-[#006c70]">“</span>
            <p className="-mt-2 text-[14px] font-bold leading-6 text-[#344244]">{whoWeAre.quote}</p>
            <span className="mt-3 block h-0.5 w-20 bg-[#f4b728]" />
          </div>
        </div>

        <div>
          <p className="text-[13px] font-extrabold uppercase tracking-[0.06em] text-[#006c70]">{whoWeAre.eyebrow}</p>
          <h2 className="mt-2 text-3xl font-extrabold tracking-[-0.035em] text-[#092d32] sm:text-[36px]">{whoWeAre.heading.slice(0, -1)}<span className="text-[#f4b728]">.</span></h2>
          <div className="mt-5 space-y-4 text-[15px] leading-7 text-[#536063]">
            {whoWeAre.paragraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
          </div>

          <div className="mt-7 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-4 lg:gap-2">
            {whoWeAre.values.map((value) => (
              <div key={value.title} className="text-center sm:text-left">
                <span className={`mx-auto grid h-10 w-10 place-items-center rounded-full sm:mx-0 ${valueColours[value.color as keyof typeof valueColours]}`}>
                  <BrandIcon name={value.icon} className="h-6 w-6" />
                </span>
                <h3 className="mt-3 text-sm font-extrabold text-[#172326]">{value.title}</h3>
                <p className="mt-1 text-[11px] leading-[1.55] text-[#606b6d]">{value.description}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
