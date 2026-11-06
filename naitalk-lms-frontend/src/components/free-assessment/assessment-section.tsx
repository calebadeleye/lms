import { BrandIcon, type BrandIconName } from '@/components/home/brand-icon';
import { AssessmentOptInForm } from '@/components/free-assessment/opt-in-form';

const BENEFITS: { icon: BrandIconName; text: string }[] = [
  { icon: 'target', text: 'Understand your strengths' },
  { icon: 'compass', text: 'Explore career paths that fit you' },
  { icon: 'star', text: 'Take your next step with confidence' },
];

/**
 * The free personality assessment opt-in, used both as the /free-assessment
 * landing page and as a section of the homepage. `headingLevel` keeps the
 * page outline right: h1 on its own page, h2 on the homepage.
 */
export function FreeAssessmentSection({
  source,
  headingLevel = 'h2',
}: {
  source: 'landing' | 'home';
  headingLevel?: 'h1' | 'h2';
}) {
  const Heading = headingLevel;

  return (
    <section id="free-assessment" aria-labelledby={`free-assessment-title-${source}`} className="relative overflow-hidden bg-linear-to-b from-[#fbf7ec] to-[#fffdf8]">
      {/* Teal and gold swoosh, bottom-left (decorative). */}
      <svg aria-hidden viewBox="0 0 400 220" className="pointer-events-none absolute -bottom-2 left-0 hidden w-[38%] max-w-[460px] lg:block">
        <path d="M0 40 C120 120 230 170 400 210 L0 220 Z" fill="#f4b728" />
        <path d="M0 95 C130 160 240 195 400 220 L0 220 Z" fill="#073d40" />
      </svg>

      <div className="relative mx-auto grid max-w-[1240px] gap-10 px-5 pb-14 pt-12 sm:px-7 lg:grid-cols-[1fr_1.05fr] lg:items-center lg:gap-6 lg:px-8 lg:pb-24 lg:pt-16">
        <div>
          <Heading
            id={`free-assessment-title-${source}`}
            className="text-[38px] font-extrabold leading-[1.08] tracking-[-0.035em] text-[#073d40] sm:text-[50px] lg:text-[58px]"
          >
            Discover Your Strengths. <span className="text-[#e3a20f]">Find Your Direction.</span>
          </Heading>
          <span aria-hidden className="mt-6 block h-1.5 w-28 rounded-full bg-[#f4b728]" />
          <p className="mt-6 max-w-[520px] text-[19px] leading-8 text-[#172326] sm:text-[21px]">
            Take our{' '}
            <span className="rounded-md bg-[#f4b728] px-1.5 py-0.5 font-extrabold text-[#073d40]">FREE</span>{' '}
            Personality Assessment and gain clarity on the career path that aligns with who you are.
          </p>

          <ul className="mt-8 space-y-4">
            {BENEFITS.map((benefit) => (
              <li key={benefit.text} className="flex items-center gap-4 text-[17px] font-semibold text-[#172326]">
                <span aria-hidden className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#f4b728]/15 text-[#e3a20f]">
                  <BrandIcon name={benefit.icon} className="h-7 w-7" />
                </span>
                {benefit.text}
              </li>
            ))}
          </ul>
        </div>

        <div className="relative">
          <div className="overflow-hidden rounded-[28px] shadow-[0_24px_60px_rgba(7,61,64,0.18)]">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src="/marketing/free-assessment-youth.jpg"
              alt="Five smiling young Black adults standing together against a teal wall"
              className="aspect-[3/2] w-full object-cover object-top"
            />
          </div>

          <div className="assessment-glow relative z-10 mx-auto -mt-16 w-[92%] max-w-[440px] lg:-mt-20 rounded-[28px] border border-[#f4b728]/60 bg-white p-6 shadow-[0_24px_60px_rgba(7,61,64,0.16)] sm:p-8 lg:ml-0 lg:w-[400px] xl:ml-[-8%]">
            <h3 className="text-[26px] font-extrabold leading-tight tracking-[-0.02em] text-[#073d40] sm:text-[30px]">
              Start Your Free Assessment
            </h3>
            <span aria-hidden className="mt-3 mb-5 block h-1 w-16 rounded-full bg-[#f4b728]" />
            <AssessmentOptInForm source={source} />
          </div>
        </div>
      </div>
    </section>
  );
}
