import { HOME_CONTENT } from '@/lib/home-content';
import { WHY_IT_MATTERS_ICONS } from '@/components/home/home-icons';

export function WhyItMattersSection() {
  const { whyItMatters } = HOME_CONTENT;

  return (
    <section className="bg-[var(--brand-primary)]/5 py-14">
      <div className="mx-auto max-w-6xl px-4 sm:px-6">
        <div className="text-center">
          <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand-accent)]">{whyItMatters.eyebrow}</p>
          <h2 className="mt-2 text-2xl font-bold text-[var(--brand-primary)] sm:text-3xl">{whyItMatters.heading}</h2>
        </div>

        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
          {whyItMatters.cards.map((card) => {
            const Icon = WHY_IT_MATTERS_ICONS[card.icon];
            return (
              <div key={card.title} className="rounded-xl border border-neutral-200 bg-white p-6 text-center">
                <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]">
                  <Icon className="h-6 w-6" />
                </div>
                <h3 className="text-sm font-semibold text-neutral-900">{card.title}</h3>
                <p className="mt-1 text-xs text-neutral-500">{card.description}</p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
