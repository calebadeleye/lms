'use client';

import { useState } from 'react';
import {
  PLAN_LIMIT_FIELDS,
  PLAN_TOGGLE_FIELDS,
  featuresToMap,
  type PlatformPlan,
} from '@/lib/platform-plan-types';
import { XIcon } from '@/components/platform/icons';

const DEFAULT_LIMITS: Record<string, string> = {
  max_administrators: '1',
  max_instructors: '1',
  max_coaches: '0',
  max_active_students: '25',
  max_published_courses: '3',
  storage_gb: '2',
  certificate_template_limit: '0',
};

export function PlanFormModal({
  plan,
  onClose,
  onSaved,
}: {
  plan: PlatformPlan | null;
  onClose: () => void;
  onSaved: (plan: PlatformPlan) => void;
}) {
  const existingFeatures = plan ? featuresToMap(plan.features) : {};

  const [name, setName] = useState(plan?.name ?? '');
  const [description, setDescription] = useState(plan?.description ?? '');
  const [billingPeriod, setBillingPeriod] = useState<PlatformPlan['billing_period']>(plan?.billing_period ?? 'monthly');
  const [priceMajor, setPriceMajor] = useState(plan ? String(plan.price_cents / 100) : '0');
  const [currency, setCurrency] = useState(plan?.currency ?? 'NGN');
  const [isPublic, setIsPublic] = useState(plan?.is_public ?? true);
  const [trialDays, setTrialDays] = useState(String(plan?.trial_days ?? 0));
  const [limits, setLimits] = useState<Record<string, string>>(() => {
    const base = { ...DEFAULT_LIMITS };
    for (const field of PLAN_LIMIT_FIELDS) {
      if (existingFeatures[field.key] !== undefined) base[field.key] = existingFeatures[field.key];
    }
    return base;
  });
  const [toggles, setToggles] = useState<Record<string, boolean>>(() =>
    Object.fromEntries(PLAN_TOGGLE_FIELDS.map((f) => [f.key, existingFeatures[f.key] === 'true']))
  );
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    const featureEntries = [
      ...PLAN_LIMIT_FIELDS.map((f) => ({ feature_key: f.key, value: limits[f.key] ?? '0' })),
      ...PLAN_TOGGLE_FIELDS.map((f) => ({ feature_key: f.key, value: toggles[f.key] ? 'true' : 'false' })),
    ];

    const basePayload = {
      name,
      description: description || null,
      billing_period: billingPeriod,
      price_cents: Math.round(Number(priceMajor) * 100),
      currency,
      is_public: isPublic,
      trial_days: Number(trialDays),
    };

    try {
      if (plan) {
        const res = await fetch(`/api/v1/platform/plans/${plan.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(basePayload),
        });
        const body = await res.json().catch(() => null);
        if (!res.ok) {
          setError(body?.errors?.name?.[0] ?? body?.errors?.[0]?.message ?? 'Could not save the plan.');
          return;
        }

        // update() doesn't accept features — each key is upserted separately.
        for (const feature of featureEntries) {
          await fetch(`/api/v1/platform/plans/${plan.id}/features`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(feature),
          });
        }

        const fresh = await fetch(`/api/v1/platform/plans`).then((r) => r.json());
        const updated = (fresh.data as PlatformPlan[]).find((p) => p.id === plan.id);
        onSaved(updated ?? { ...plan, ...basePayload, features: featureEntries });
      } else {
        const res = await fetch('/api/v1/platform/plans', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ...basePayload, features: featureEntries }),
        });
        const body = await res.json().catch(() => null);
        if (!res.ok) {
          setError(body?.errors?.name?.[0] ?? body?.errors?.[0]?.message ?? 'Could not create the plan.');
          return;
        }
        onSaved(body.data);
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
      <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-white/10 bg-[#0b1220] p-6 shadow-2xl">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-white">{plan ? 'Edit plan' : 'Create plan'}</h2>
          <button onClick={onClose} className="rounded-full p-1.5 text-white/50 hover:bg-white/10 hover:text-white">
            <XIcon className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={submit} className="mt-5 space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Plan name">
              <input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Starter (Monthly)"
                className={inputClass}
              />
            </Field>
            <Field label="Billing period">
              <select
                value={billingPeriod}
                onChange={(e) => setBillingPeriod(e.target.value as PlatformPlan['billing_period'])}
                className={inputClass}
              >
                <option value="free" className="bg-slate-900">Free</option>
                <option value="monthly" className="bg-slate-900">Monthly</option>
                <option value="annual" className="bg-slate-900">Annual</option>
                <option value="trial" className="bg-slate-900">Trial</option>
                <option value="custom" className="bg-slate-900">Custom</option>
              </select>
            </Field>
            <Field label="Price">
              <input
                type="number"
                min="0"
                step="0.01"
                value={priceMajor}
                onChange={(e) => setPriceMajor(e.target.value)}
                className={inputClass}
              />
            </Field>
            <Field label="Currency">
              <select value={currency} onChange={(e) => setCurrency(e.target.value)} className={inputClass}>
                <option value="NGN" className="bg-slate-900">NGN</option>
                <option value="USD" className="bg-slate-900">USD</option>
              </select>
            </Field>
            <Field label="Trial days">
              <input
                type="number"
                min="0"
                value={trialDays}
                onChange={(e) => setTrialDays(e.target.value)}
                className={inputClass}
              />
            </Field>
            <div className="flex items-end pb-2">
              <label className="flex items-center gap-2 text-sm text-white/80">
                <input type="checkbox" checked={isPublic} onChange={(e) => setIsPublic(e.target.checked)} />
                Public (shown to prospective tenants)
              </label>
            </div>
          </div>

          <Field label="Description">
            <textarea
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className={inputClass}
            />
          </Field>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-wide text-white/40">Limits</h3>
            <div className="mt-2 grid gap-3 sm:grid-cols-2">
              {PLAN_LIMIT_FIELDS.map((field) => (
                <Field key={field.key} label={field.label}>
                  <input
                    type="number"
                    min="0"
                    value={limits[field.key] ?? '0'}
                    onChange={(e) => setLimits((prev) => ({ ...prev, [field.key]: e.target.value }))}
                    className={inputClass}
                  />
                </Field>
              ))}
            </div>
          </div>

          <div>
            <h3 className="text-xs font-semibold uppercase tracking-wide text-white/40">Features</h3>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              {PLAN_TOGGLE_FIELDS.map((field) => (
                <label key={field.key} className="flex items-center gap-2 text-sm text-white/80">
                  <input
                    type="checkbox"
                    checked={toggles[field.key] ?? false}
                    onChange={(e) => setToggles((prev) => ({ ...prev, [field.key]: e.target.checked }))}
                  />
                  {field.label}
                </label>
              ))}
            </div>
          </div>

          {error && <p className="text-sm text-red-400">{error}</p>}

          <div className="flex justify-end gap-3 border-t border-white/10 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-white/80 hover:bg-white/5"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={pending}
              className="rounded-full bg-[var(--naitalk-green)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >
              {pending ? 'Saving…' : plan ? 'Save changes' : 'Create plan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

const inputClass =
  'mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--naitalk-green)] focus:outline-none';

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-xs font-medium text-white/60">{label}</label>
      {children}
    </div>
  );
}
