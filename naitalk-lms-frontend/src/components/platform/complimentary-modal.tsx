'use client';

import { useState } from 'react';
import type { SubscribedTenant, TenantSubscription } from '@/lib/platform-subscription-types';
import type { PlatformPlan } from '@/lib/platform-plan-types';
import { XIcon } from '@/components/platform/icons';

export function ComplimentaryModal({
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
  const [planId, setPlanId] = useState(tenant.subscriptions?.[0]?.plan_id.toString() ?? plans[0]?.id.toString() ?? '');
  const [reason, setReason] = useState('');
  const [until, setUntil] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/platform/tenants/${tenant.id}/subscription/complimentary`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ plan_id: Number(planId), reason, until: until || undefined }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.reason?.[0] ?? body?.errors?.[0]?.message ?? 'Could not grant complimentary access.');
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
          <h2 className="text-lg font-bold text-white">Grant complimentary access</h2>
          <button onClick={onClose} className="rounded-full p-1.5 text-white/50 hover:bg-white/10 hover:text-white">
            <XIcon className="h-5 w-5" />
          </button>
        </div>
        <p className="mt-1 text-xs text-white/50">{tenant.name} — no invoice is ever created for this.</p>

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
            <label className="block text-xs font-medium text-white/60">Reason</label>
            <input
              required
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="e.g. Partner academy, non-profit discount"
              className={inputClass}
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-white/60">Until (optional — leave blank for permanent)</label>
            <input type="date" value={until} onChange={(e) => setUntil(e.target.value)} className={inputClass} />
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
              disabled={pending || !reason.trim()}
              className="rounded-full bg-purple-500 px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >
              {pending ? 'Granting…' : 'Grant access'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

const inputClass =
  'mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--naitalk-green)] focus:outline-none';
