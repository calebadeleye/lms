'use client';

import { PLAN_LIMIT_FIELDS, PLAN_TOGGLE_FIELDS, featuresToMap, type PlatformPlan } from '@/lib/platform-plan-types';
import { XIcon } from '@/components/platform/icons';
import { formatPlanPrice } from '@/lib/platform-plan-format';

export function PlanViewModal({ plan, onClose }: { plan: PlatformPlan; onClose: () => void }) {
  const features = featuresToMap(plan.features);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
      <div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-white/10 bg-[#0b1220] p-6 shadow-2xl">
        <div className="flex items-start justify-between">
          <div>
            <h2 className="text-lg font-bold text-white">{plan.name}</h2>
            <p className="mt-1 text-2xl font-bold text-[var(--naitalk-green)]">
              {formatPlanPrice(plan.price_cents, plan.currency)}
              {plan.price_cents > 0 && (
                <span className="text-sm font-normal text-white/50">
                  /{plan.billing_period === 'annual' ? 'yr' : 'mo'}
                </span>
              )}
            </p>
          </div>
          <button onClick={onClose} className="rounded-full p-1.5 text-white/50 hover:bg-white/10 hover:text-white">
            <XIcon className="h-5 w-5" />
          </button>
        </div>

        {plan.description && <p className="mt-3 text-sm text-white/60">{plan.description}</p>}

        <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          {PLAN_LIMIT_FIELDS.map((field) => (
            <div key={field.key} className="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2">
              <span className="text-white/60">{field.label}</span>
              <span className="font-semibold text-white">{features[field.key] ?? '—'}</span>
            </div>
          ))}
        </div>

        <h3 className="mt-5 text-xs font-semibold uppercase tracking-wide text-white/40">Included features</h3>
        <div className="mt-2 flex flex-wrap gap-2">
          {PLAN_TOGGLE_FIELDS.filter((field) => features[field.key] === 'true').map((field) => (
            <span
              key={field.key}
              className="rounded-full bg-[var(--naitalk-green)]/15 px-2.5 py-1 text-xs font-medium text-[var(--naitalk-green)]"
            >
              {field.label}
            </span>
          ))}
          {PLAN_TOGGLE_FIELDS.every((field) => features[field.key] !== 'true') && (
            <span className="text-sm text-white/40">None enabled.</span>
          )}
        </div>

        <div className="mt-5 flex items-center justify-between border-t border-white/10 pt-4 text-xs text-white/40">
          <span>Code: {plan.code}</span>
          <span>{plan.is_public ? 'Public' : 'Private'} · {plan.trial_days} day trial</span>
        </div>
      </div>
    </div>
  );
}
