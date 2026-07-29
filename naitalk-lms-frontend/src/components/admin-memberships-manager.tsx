'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { formatPrice } from '@/lib/learning-types';

interface AdminMembershipPlan {
  id: number;
  name: string;
  billing_period: 'monthly' | 'annual' | 'free';
  price_cents: number;
  currency: string;
  benefits: string[] | null;
  is_active: boolean;
  subscriptions_count: number;
}

const emptyForm = {
  name: '',
  billing_period: 'monthly' as 'monthly' | 'annual' | 'free',
  priceNaira: '0',
  currency: 'NGN',
  benefits: '',
};

export function AdminMembershipsManager({ initial }: { initial: AdminMembershipPlan[] }) {
  const router = useRouter();
  const [plans, setPlans] = useState(initial);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function refresh() {
    const res = await fetch('/api/v1/admin/membership-plans');
    const body = await res.json();
    setPlans(body.data ?? []);
  }

  async function createPlan(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);
    try {
      const res = await fetch('/api/v1/admin/membership-plans', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: form.name,
          billing_period: form.billing_period,
          price_cents: form.billing_period === 'free' ? 0 : Math.round(Number(form.priceNaira) * 100),
          currency: form.currency,
          benefits: form.benefits
            .split('\n')
            .map((b) => b.trim())
            .filter(Boolean),
          is_active: true,
        }),
      });
      const body = await res.json().catch(() => null);
      if (!res.ok) {
        setError(body?.errors?.name?.[0] ?? 'Could not create this plan.');
        return;
      }
      setForm(emptyForm);
      setShowForm(false);
      await refresh();
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function toggleActive(plan: AdminMembershipPlan) {
    if (plan.is_active) {
      await fetch(`/api/v1/admin/membership-plans/${plan.id}`, { method: 'DELETE' });
    } else {
      await fetch(`/api/v1/admin/membership-plans/${plan.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_active: true }),
      });
    }
    await refresh();
    router.refresh();
  }

  return (
    <div className="space-y-4">
      <div className="rounded-xl border border-neutral-200 bg-white p-5">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-neutral-900">Membership Plans</h2>
          <button onClick={() => setShowForm((v) => !v)} className="text-xs font-semibold text-[var(--tenant-primary)] hover:underline">
            {showForm ? 'Cancel' : '+ New plan'}
          </button>
        </div>

        {showForm && (
          <form onSubmit={createPlan} className="mt-4 space-y-3 rounded-lg border border-neutral-200 p-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <label className="block text-xs font-medium text-neutral-700">Name</label>
                <input
                  required
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-neutral-700">Billing period</label>
                <select
                  value={form.billing_period}
                  onChange={(e) => setForm((f) => ({ ...f, billing_period: e.target.value as typeof form.billing_period }))}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                >
                  <option value="monthly">Monthly</option>
                  <option value="annual">Annual</option>
                  <option value="free">Free</option>
                </select>
              </div>
            </div>

            {form.billing_period !== 'free' && (
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <label className="block text-xs font-medium text-neutral-700">Price (₦)</label>
                  <input
                    type="number"
                    min={0}
                    value={form.priceNaira}
                    onChange={(e) => setForm((f) => ({ ...f, priceNaira: e.target.value }))}
                    className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-neutral-700">Currency</label>
                  <input
                    value={form.currency}
                    onChange={(e) => setForm((f) => ({ ...f, currency: e.target.value.toUpperCase() }))}
                    maxLength={3}
                    className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
                  />
                </div>
              </div>
            )}

            <div>
              <label className="block text-xs font-medium text-neutral-700">Benefits (one per line)</label>
              <textarea
                value={form.benefits}
                onChange={(e) => setForm((f) => ({ ...f, benefits: e.target.value }))}
                rows={3}
                placeholder={'Members-only courses\nPriority support'}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
              />
            </div>

            {error && <p className="text-xs text-red-600">{error}</p>}

            <button
              type="submit"
              disabled={pending}
              className="rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
            >
              {pending ? 'Creating…' : 'Create plan'}
            </button>
          </form>
        )}
      </div>

      <div className="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        {plans.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">No membership plans yet.</p>
        ) : (
          <ul className="divide-y divide-neutral-100">
            {plans.map((plan) => (
              <li key={plan.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                  <p className="text-sm font-medium text-neutral-900">{plan.name}</p>
                  <p className="mt-0.5 text-xs text-neutral-500">
                    {formatPrice(plan.price_cents, plan.currency)}
                    {plan.billing_period !== 'free' && `/${plan.billing_period}`} &middot; {plan.subscriptions_count} subscriber
                    {plan.subscriptions_count === 1 ? '' : 's'}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                      plan.is_active ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-600'
                    }`}
                  >
                    {plan.is_active ? 'Active' : 'Inactive'}
                  </span>
                  <button onClick={() => toggleActive(plan)} className="text-xs font-semibold text-[var(--tenant-primary)] hover:underline">
                    {plan.is_active ? 'Deactivate' : 'Reactivate'}
                  </button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
