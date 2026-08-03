import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { BrandIcon } from '@/components/home/brand-icon';

export function ProgrammesSection() {
  const { programmes } = HOME_CONTENT;

  return (
    <section id="programmes" className="bg-[#f7f6f0]/55 px-5 py-16 sm:px-7 sm:py-20 lg:px-8">
      <div className="mx-auto max-w-[1060px]">
        <div className="grid items-end gap-5 md:grid-cols-[1fr_1fr]">
          <div>
            <p className="text-[13px] font-extrabold uppercase tracking-[0.06em] text-[#006c70]">{programmes.eyebrow}</p>
            <h2 className="mt-2 max-w-[390px] text-3xl font-extrabold leading-[1.08] tracking-[-0.035em] text-[#092d32] sm:text-[38px]">Pathways to Your<br />Next Level</h2>
          </div>
          <div className="pb-1 md:max-w-[410px] md:justify-self-end">
            <p className="text-[15px] leading-6 text-[#5a6668]">{programmes.description}</p>
            <Link href="/courses" className="group mt-3 inline-flex items-center gap-2 text-[13px] font-extrabold text-[#006c70]">View All Courses <span aria-hidden className="transition-transform group-hover:translate-x-1">→</span></Link>
          </div>
        </div>

        <div className="mt-8 grid gap-5 md:grid-cols-3">
          {programmes.items.map((item, index) => (
            <Link key={item.title} href={item.href} className="group flex min-h-full flex-col overflow-hidden rounded-2xl border border-[#dce5e2] bg-white transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_32px_rgba(7,61,64,0.09)]">
              <div className="relative h-[205px] overflow-hidden">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={item.image} alt={item.imageAlt} className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" />
                <span className={`absolute -bottom-6 left-5 grid h-14 w-14 place-items-center rounded-full border-[3px] border-white text-white ${index === 1 ? 'bg-[#f4b728]' : 'bg-[#006c70]'}`}>
                  <BrandIcon name={item.icon} className="h-7 w-7" />
                </span>
              </div>
              <div className="flex flex-1 flex-col px-5 pb-5 pt-9">
                <h3 className="text-[20px] font-extrabold leading-6 tracking-[-0.025em] text-[#142629]">{item.title}</h3>
                <p className="mt-3 text-[14px] leading-6 text-[#5a6668]">{item.description}</p>
                <span className="mt-auto inline-flex items-center gap-2 pt-5 text-[13px] font-extrabold text-[#006c70]">{item.action}<span aria-hidden className="transition-transform group-hover:translate-x-1">→</span></span>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
