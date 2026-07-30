import { HOME_CONTENT } from '@/lib/home-content';
import { WHY_IT_MATTERS_ICONS } from '@/components/home/home-icons';

export function WhyItMattersSection() {
  const { whyItMatters } = HOME_CONTENT;

  return (
    <section className="px-4 py-16 sm:px-8 sm:py-20">
      <div className="mx-auto max-w-[1120px] rounded-lg border border-[var(--brand-primary)]/10 bg-[#eefafa] px-4 py-8 shadow-[0_14px_32px_rgba(15,23,42,0.08)] sm:px-6 sm:py-10">
        <div className="text-center">
          <p className="text-[11px] font-black uppercase text-[var(--brand-primary)]">{whyItMatters.eyebrow}</p>
          <h2
            className="mt-1 text-[26px] font-black leading-tight text-[#082f35] sm:text-[30px]"
            style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
          >
            {whyItMatters.heading}
          </h2>
        </div>

        <div className="mt-5 grid gap-5 sm:grid-cols-2 min-[900px]:grid-cols-5">
          {whyItMatters.cards.map((card) => {
            const Icon = WHY_IT_MATTERS_ICONS[card.icon];
            return (
              <div
                key={card.title}
                className="grid min-h-[136px] grid-cols-[44px_1fr] gap-3 rounded-lg bg-white px-5 py-5 shadow-[0_10px_24px_rgba(15,23,42,0.08)]"
              >
                <div className="pt-1 text-[var(--brand-primary)]">
                  <Icon className="h-10 w-10" />
                </div>
                <div>
                  <h3 className="text-[13px] font-black leading-snug text-[var(--brand-primary)]">{card.title}</h3>
                  <p className="mt-2 text-[11px] font-medium leading-relaxed text-neutral-900">{card.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
