'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { CheckoutButton } from '@/components/checkout-button';
import { formatPrice } from '@/lib/learning-types';
import type { MembershipPlan, MySubscription } from '@/lib/membership-types';

export function MembershipPlanCard({
  plan,
  mySubscription,
  isAuthenticated,
}: {
  plan: MembershipPlan;
  mySubscription: MySubscription | null;
  isAuthenticated: boolean;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isCurrent = mySubscription?.plan.id === plan.id && mySubscription.status === 'active';

  async function cancel() {
    if (!mySubscription) return;
    setPending(true);
    setError(null);
    try {
      await fetch(`/api/v1/my/membership/${mySubscription.id}/cancel`, { method: 'POST' });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div
      className={`flex flex-col rounded-xl border bg-white p-6 ${
        isCurrent ? 'border-[var(--brand-accent)] ring-1 ring-[var(--brand-accent)]' : 'border-neutral-200'
      }`}
    >
      {isCurrent && (
        <span className="mb-3 inline-block w-fit rounded-full bg-[var(--brand-accent)]/15 px-2.5 py-0.5 text-xs font-semibold text-[var(--brand-accent)]">
          Current Plan
        </span>
      )}
      <h3 className="text-lg font-bold text-neutral-900">{plan.name}</h3>
      <p className="mt-1 text-2xl font-bold text-neutral-900">
        {formatPrice(plan.price_cents, plan.currency)}
        <span className="text-sm font-normal text-neutral-500">/{plan.billing_period}</span>
      </p>

      {plan.benefits && plan.benefits.length > 0 && (
        <ul className="mt-4 flex-1 space-y-2 text-sm text-neutral-600">
          {plan.benefits.map((benefit, i) => (
            <li key={i}>✓ {benefit}</li>
          ))}
        </ul>
      )}

      <div className="mt-6">
        {isCurrent ? (
          mySubscription?.cancel_at_period_end ? (
            <p className="text-xs text-neutral-500">Cancels at the end of the current period.</p>
          ) : (
            <button
              onClick={cancel}
              disabled={pending}
              className="w-full rounded-md border border-neutral-300 px-5 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 disabled:opacity-60"
            >
              Cancel plan
            </button>
          )
        ) : !isAuthenticated ? (
          <button
            onClick={() => router.push('/login?redirect=/membership')}
            className="w-full rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90"
          >
            Log in to Subscribe
          </button>
        ) : (
          <CheckoutButton
            kind="membership-plans"
            id={plan.id}
            label="Subscribe"
            pendingLabel="Redirecting to payment…"
            className="w-full rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
          />
        )}
        {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
      </div>
    </div>
  );
}
