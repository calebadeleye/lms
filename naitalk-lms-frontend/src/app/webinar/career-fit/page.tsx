import type { Metadata } from 'next';
import Link from 'next/link';
import { Caveat } from 'next/font/google';
import { BRANDING } from '@/lib/branding';
import { SiteFooter } from '@/components/site-footer';
import { CheckIcon, CalendarIcon, ClockIcon, VideoIcon, DecisionIcon } from '@/components/home/home-icons';

// Re-evaluated on every request (not baked in at build time) so the page
// switches itself from "register" to "ended" state on its own once the
// event has passed — no redeploy or manual toggle needed.
export const dynamic = 'force-dynamic';

// Scoped to this page only (not added to the global layout) for the
// flyer's cursive accent lettering.
const caveat = Caveat({ subsets: ['latin'], weight: ['600', '700'] });

const ZOOM_REGISTRATION_URL = 'https://us06web.zoom.us/meeting/register/sBnDImtDTOmZRqiuWOIhEQ';

// Registration stops being offered at the end of the event day (WAT,
// UTC+1). Nothing else needs to change for the page to "turn off".
const REGISTRATION_CLOSES_AT = new Date('2026-08-16T00:00:00+01:00');

const WHAT_TO_EXPECT = [
  'Discover your strengths, values & personality',
  'Explore career paths that align with who you are',
  'Get practical steps to build your future with clarity and confidence',
];

const AUDIENCE = ['Students', 'Recent graduates', 'Young professionals seeking direction'];

const SPEAKERS = [
  { name: 'Miriam Balogun', role: 'Learning & Development Facilitator / Business Entrepreneur', photo: '/webinar/speaker-miriam.jpeg' },
  { name: 'Saint of Africa', role: 'Founder, Saint HR Solution', photo: '/webinar/speaker-saint.jpeg' },
  { name: 'Lara Yeku', role: 'Certified Coach, GM, Business Transformation', photo: '/webinar/speaker-lara.jpeg' },
];

export const metadata: Metadata = {
  title: 'Career F.I.T. Webinar — International Youth Day',
  description:
    'A FREE Career Clarity (Career F.I.T.) Webinar from HR GEMs Coach Network, in commemoration of ' +
    'UN International Youth Day, helping young people discover careers that align with who they are.',
  robots: { index: false, follow: false },
};

export default function CareerFitWebinarPage() {
  const config = BRANDING;
  const registrationOpen = Date.now() < REGISTRATION_CLOSES_AT.getTime();

  return (
    <div className="flex min-h-screen flex-col">
      <main className="flex-1">
        <section className="relative overflow-hidden bg-[var(--brand-primary)]/5">
          {/* Decorative swoosh + dot pattern, echoing the campaign flyer's corner motifs */}
          <div
            aria-hidden
            className="pointer-events-none absolute -top-28 -left-28 h-72 w-72 rounded-full bg-[var(--brand-primary)]"
          />
          <div
            aria-hidden
            className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full border-[6px] border-[var(--brand-accent)]/70"
          />
          <div
            aria-hidden
            className="pointer-events-none absolute -top-6 right-0 hidden h-56 w-56 opacity-25 sm:block"
            style={{
              backgroundImage: 'radial-gradient(var(--brand-primary) 1.5px, transparent 1.5px)',
              backgroundSize: '16px 16px',
            }}
          />

          <div className="relative mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 sm:py-20">
            <span className="relative inline-flex items-center gap-2 rounded-full bg-[var(--brand-accent)] px-5 py-1.5 text-xs font-bold uppercase tracking-wide text-neutral-900">
              United Nations International Youth Day
            </span>

            <h1 className="mt-5 text-3xl font-extrabold sm:text-5xl">
              <span className="text-[var(--brand-primary)]">Find Your Career </span>
              <span className="text-[var(--brand-accent)]">F.I.T.</span>
            </h1>
            <p className={`${caveat.className} mt-1 text-2xl text-[var(--brand-accent)] sm:text-3xl`}>
              A Free Career Clarity Webinar
            </p>

            <p className="mx-auto mt-5 max-w-2xl text-sm text-neutral-600">
              In commemoration of International Youth Day, HR GEMs Coach Network presents a FREE Career Clarity
              (Career F.I.T.) Webinar designed to help young people discover careers that align with who they are.
            </p>

            <div className="mx-auto mt-6 max-w-2xl rounded-xl bg-[var(--brand-primary)] px-6 py-5 text-sm font-semibold text-white sm:text-base">
              Discover who you are. Clarify where you&rsquo;re going.
              <br />
              Map a career that truly fits <span className="text-[var(--brand-accent)]">YOU</span>.
            </div>

            {registrationOpen ? (
              <>
                <div className="mx-auto mt-8 flex max-w-lg flex-col items-center gap-3 rounded-xl border border-[var(--brand-primary)]/20 bg-white p-6 shadow-sm sm:flex-row sm:justify-center sm:gap-6">
                  <div className="flex items-center gap-2">
                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white">
                      <CalendarIcon className="h-4 w-4" />
                    </span>
                    <span className="text-left text-sm text-neutral-700">
                      <span className="block font-semibold text-[var(--brand-primary)]">Date</span>
                      15th August 2026
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white">
                      <ClockIcon className="h-4 w-4" />
                    </span>
                    <span className="text-left text-sm text-neutral-700">
                      <span className="block font-semibold text-[var(--brand-primary)]">Time</span>
                      6:00 PM (WAT)
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white">
                      <VideoIcon className="h-4 w-4" />
                    </span>
                    <span className="text-left text-sm text-neutral-700">
                      <span className="block font-semibold text-[var(--brand-primary)]">Venue</span>
                      Zoom
                    </span>
                  </div>
                </div>
                <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                  Registration: FREE &mdash; Limited Slots Available
                </p>
                <a
                  href={ZOOM_REGISTRATION_URL}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-5 inline-block rounded-full bg-[var(--brand-accent)] px-8 py-3 text-sm font-bold text-neutral-900 hover:opacity-90"
                >
                  Register Now &rarr;
                </a>
              </>
            ) : (
              <div className="mx-auto mt-8 max-w-md rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-700 shadow-sm">
                <p className="font-semibold text-[var(--brand-primary)]">This webinar has already taken place.</p>
                <p className="mt-2">
                  Thanks to everyone who joined us! Registration for this session is now closed, but you can keep
                  building your Career F.I.T. below.
                </p>
                <Link
                  href="/courses/find-your-career-fit"
                  className="mt-4 inline-block rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-neutral-900 hover:opacity-90"
                >
                  Take the Free Career Fit Course &rarr;
                </Link>
              </div>
            )}
          </div>
        </section>

        <section className="mx-auto max-w-5xl px-4 pt-16 pb-16 sm:px-6">
          <p className="text-center text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">Speakers</p>
          <div className="mt-6 grid gap-6 sm:grid-cols-3">
            {SPEAKERS.map((speaker) => (
              <div key={speaker.name} className="overflow-hidden rounded-xl border border-neutral-200 text-center">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={speaker.photo}
                  alt={speaker.name}
                  className="aspect-[3/4] w-full border-b-4 border-[var(--brand-accent)] object-cover object-top"
                />
                <div className="p-4">
                  <p className="rounded-full bg-[var(--brand-primary)] px-3 py-1 text-sm font-bold text-white">
                    {speaker.name}
                  </p>
                  <p className="mt-2 text-xs text-neutral-600">{speaker.role}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-4xl px-4 pb-16 sm:px-6">
          <div className="grid gap-6 sm:grid-cols-2">
            <div className="rounded-xl border border-[var(--brand-accent)]/40 p-6">
              <div className="flex items-center gap-2">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white">
                  <DecisionIcon className="h-4 w-4" />
                </span>
                <h3 className="text-lg font-bold text-[var(--brand-primary)]">What to Expect</h3>
              </div>
              <ul className="mt-4 space-y-3">
                {WHAT_TO_EXPECT.map((item) => (
                  <li key={item} className="flex items-start gap-2 text-sm text-neutral-700">
                    <span
                      aria-hidden
                      className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--brand-accent)] text-neutral-900"
                    >
                      <CheckIcon className="h-3 w-3" />
                    </span>
                    {item}
                  </li>
                ))}
              </ul>
            </div>

            <div className="rounded-xl bg-[var(--brand-accent)]/10 p-6">
              <h3 className="text-lg font-bold text-[var(--brand-primary)]">Who This Webinar Is For</h3>
              <ul className="mt-4 space-y-3">
                {AUDIENCE.map((item) => (
                  <li key={item} className="flex items-start gap-2 text-sm text-neutral-700">
                    <span
                      aria-hidden
                      className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white"
                    >
                      <CheckIcon className="h-3 w-3" />
                    </span>
                    {item}
                  </li>
                ))}
              </ul>
              <p className="mt-5 text-sm text-neutral-700">
                This webinar is your opportunity to gain the clarity needed to move forward with confidence.
                Don&rsquo;t leave your career to chance.
              </p>
            </div>
          </div>
        </section>

        <section className="relative overflow-hidden bg-[var(--brand-primary)] py-14 text-center text-white">
          <div className="mx-auto flex max-w-2xl flex-col items-center px-4">
            <div className="grid h-28 w-28 place-items-center rounded-full border-2 border-white/40 text-xs font-bold uppercase leading-tight">
              Clarity. Confidence. Career Fit.
            </div>
            <p className={`${caveat.className} mt-3 text-2xl text-[var(--brand-accent)] sm:text-3xl`}>
              That&rsquo;s the F.I.T!
            </p>
            <p className="mt-4 text-lg font-bold sm:text-xl">
              Discover Yourself. Find Your Career F.I.T. Shape Your Future.
            </p>
            {registrationOpen && (
              <a
                href={ZOOM_REGISTRATION_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-6 inline-block rounded-full bg-[var(--brand-accent)] px-8 py-3 text-sm font-bold text-neutral-900 hover:opacity-90"
              >
                Register Now &rarr;
              </a>
            )}
            <p className={`${caveat.className} mt-6 text-xl text-white/80`}>Stronger Coaches. Greater Impact.</p>
          </div>
        </section>
      </main>

      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
