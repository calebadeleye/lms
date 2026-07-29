'use client';

import { useState } from 'react';
import type { SubscribedTenant, SubscriptionStatus, TenantSubscription } from '@/lib/platform-subscription-types';
import type { PlatformPlan } from '@/lib/platform-plan-types';
import { XIcon } from '@/components/platform/icons';

const STATUS_OPTIONS: SubscriptionStatus[] = [
  'trialing',
  'active',
  'grace_period',
  'past_due',
  'suspended',
  'cancelled',
  'complimentary',
];

export function SubscriptionFormModal({
  tenant,
  plans,
  onClose,
  onSaved,
}: {
  tenant: SubscribedTenant;
  plans: PlatformPlan[];
  onClose: () => void;
  onSaved: (subscription: TenantSubscription) => void;
}) {
  const current = tenant.subscriptions?.[0];
  const [planId, setPlanId] = useState(current?.plan_id.toString() ?? plans[0]?.id.toString() ?? '');
  const [status, setStatus] = useState<SubscriptionStatus>(current?.status ?? 'active');
  const [periodEnd, setPeriodEnd] = useState(current?.current_period_end?.slice(0, 10) ?? '');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/platform/tenants/${tenant.id}/subscription`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          plan_id: Number(planId),
          status,
          current_period_end: periodEnd || undefined,
        }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.plan_id?.[0] ?? body?.errors?.[0]?.message ?? 'Could not update the subscription.');
        return;
      }

      onSaved(body.data);
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#0b1220] p-6 shadow-2xl">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-white">{tenant.name}</h2>
          <button onClick={onClose} className="rounded-full p-1.5 text-white/50 hover:bg-white/10 hover:text-white">
            <XIcon className="h-5 w-5" />
          </button>
        </div>
        <p className="mt-1 text-xs text-white/50">Change plan or subscription status</p>

        <form onSubmit={submit} className="mt-5 space-y-4">
          <div>
            <label className="block text-xs font-medium text-white/60">Plan</label>
            <select value={planId} onChange={(e) => setPlanId(e.target.value)} className={inputClass}>
              {plans.map((plan) => (
                <option key={plan.id} value={plan.id} className="bg-slate-900">
                  {plan.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-white/60">Status</label>
            <select value={status} onChange={(e) => setStatus(e.target.value as SubscriptionStatus)} className={inputClass}>
              {STATUS_OPTIONS.map((s) => (
                <option key={s} value={s} className="bg-slate-900">
                  {s.replace('_', ' ')}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-white/60">Current period end (optional)</label>
            <input type="date" value={periodEnd} onChange={(e) => setPeriodEnd(e.target.value)} className={inputClass} />
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
              {pending ? 'Saving…' : 'Save changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

const inputClass =
  'mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-[var(--naitalk-green)] focus:outline-none';
