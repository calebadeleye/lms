import type { Metadata } from 'next';
import Link from 'next/link';
import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { FreeAssessmentSection } from '@/components/free-assessment/assessment-section';

export const metadata: Metadata = {
  title: 'Free Personality Assessment',
  description:
    'Take the free HR GEMs personality assessment: discover your strengths and find the career path that fits who you are. No payment required.',
};

const STEPS = [
  { title: 'Leave your name and email', text: 'It takes less than a minute, and it’s completely free.' },
  { title: 'Check your inbox', text: 'We’ll email you your personal link to the assessment straight away.' },
  { title: 'Discover your personality', text: 'Learn what makes you unique, then take the next step with our Find Your Career Fit course.' },
];

export default async function FreeAssessmentPage() {
  const config = BRANDING;
  const user = await getOptionalUser();

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding.logo_url} isAuthenticated={user !== null} />

      <main className="flex-1">
        <FreeAssessmentSection source="landing" headingLevel="h1" />

        <section aria-labelledby="how-it-works" className="mx-auto max-w-[1100px] px-5 py-16 sm:px-7">
          <h2 id="how-it-works" className="text-center text-[28px] font-extrabold tracking-[-0.02em] text-[#073d40] sm:text-[34px]">
            How it works
          </h2>
          <ol className="mt-10 grid gap-6 md:grid-cols-3">
            {STEPS.map((step, i) => (
              <li key={step.title} className="rounded-2xl border border-[#e7ecea] bg-white p-6 shadow-[0_8px_24px_rgba(7,61,64,0.06)]">
                <span className="grid h-10 w-10 place-items-center rounded-full bg-[#073d40] text-sm font-extrabold text-[#f4b728]">{i + 1}</span>
                <h3 className="mt-4 text-lg font-extrabold text-[#073d40]">{step.title}</h3>
                <p className="mt-2 text-sm leading-6 text-[#3f4c4e]">{step.text}</p>
              </li>
            ))}
          </ol>
          <p className="mt-10 text-center text-sm text-[#3f4c4e]">
            Already know your personality type?{' '}
            <Link href="/courses/find-your-career-fit" className="font-bold text-[#006c70] underline">
              Go straight to the Find Your Career Fit course
            </Link>
            .
          </p>
        </section>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
