import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { CheckIcon, MEMBERSHIP_BENEFIT_ICONS } from '@/components/home/home-icons';

export function JoinSection() {
  const { whoShouldJoin, membershipBenefits, memberVoices } = HOME_CONTENT;

  return (
    <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
      <div className="grid gap-6 lg:grid-cols-3">
        <div className="rounded-xl bg-[var(--brand-primary)]/5 p-6">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{whoShouldJoin.eyebrow}</p>
          <h3 className="mt-2 text-lg font-bold text-[var(--brand-primary)]">{whoShouldJoin.heading}</h3>
          <ul className="mt-4 space-y-3">
            {whoShouldJoin.items.map((item) => (
              <li key={item} className="flex items-start gap-2 text-sm text-neutral-700">
                <span aria-hidden className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-white">
                  <CheckIcon className="h-3 w-3" />
                </span>
                {item}
              </li>
            ))}
          </ul>
        </div>

        <div className="rounded-xl bg-[var(--brand-accent)]/10 p-6">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{membershipBenefits.eyebrow}</p>
          <h3 className="mt-2 text-lg font-bold text-[var(--brand-primary)]">{membershipBenefits.heading}</h3>
          <ul className="mt-4 space-y-3">
            {membershipBenefits.items.map((item, i) => {
              const Icon = MEMBERSHIP_BENEFIT_ICONS[item.icon];
              return (
                <li key={i} className="flex items-start gap-2 text-sm text-neutral-700">
                  <span aria-hidden className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--brand-accent)] text-neutral-900">
                    <Icon className="h-3 w-3" />
                  </span>
                  {item.text}
                </li>
              );
            })}
          </ul>
        </div>

        <div className="rounded-xl border border-neutral-200 p-6">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{memberVoices.eyebrow}</p>
          <h3 className="mt-2 text-lg font-bold text-[var(--brand-primary)]">{memberVoices.heading}</h3>
          <ul className="mt-4 space-y-4">
            {memberVoices.testimonials.map((testimonial) => (
              <li key={testimonial.author} className="flex items-start gap-3">
                <span
                  aria-hidden
                  className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-primary)] text-xs font-bold text-white"
                >
                  {testimonial.author.charAt(0)}
                </span>
                <div>
                  <p className="text-xs italic text-neutral-600">&ldquo;{testimonial.quote}&rdquo;</p>
                  <p className="mt-1 text-xs font-semibold text-[var(--brand-primary)]">&mdash; {testimonial.author}</p>
                </div>
              </li>
            ))}
          </ul>
          <Link href={memberVoices.moreLink.href} className="mt-4 inline-block text-xs font-semibold text-[var(--brand-primary)] hover:underline">
            {memberVoices.moreLink.label} &rarr;
          </Link>
        </div>
      </div>
    </section>
  );
}
