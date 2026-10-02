'use client';

import { useState } from 'react';
import { formatPrice } from '@/lib/learning-types';

const FEE_CENTS = 2_500_000;
const FEE_CURRENCY = 'NGN';

export function MembershipPayModal({ className }: { className?: string }) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const callback_url = `${window.location.origin}/membership`;
      const res = await fetch('/api/v1/checkout/membership-fee/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, callback_url }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok || !body?.data?.authorization_url) {
        setError(body?.errors?.payment?.[0] ?? body?.message ?? 'Something went wrong. Please try again.');
        return;
      }

      window.location.href = body.data.authorization_url;
    } finally {
      setPending(false);
    }
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className={className ?? 'rounded-md bg-[var(--brand-accent)] px-6 py-3 text-sm font-semibold text-neutral-900 hover:opacity-90'}
      >
        Pay with Paystack
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4" role="dialog" aria-modal="true">
          <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            <div className="flex items-start justify-between">
              <h2 className="text-lg font-bold text-neutral-900">Pay membership fee</h2>
              <button
                type="button"
                onClick={() => setOpen(false)}
                aria-label="Close"
                className="text-neutral-400 hover:text-neutral-600"
              >
                &times;
              </button>
            </div>
            <p className="mt-1 text-sm text-neutral-600">
              A one-time membership fee of <span className="font-semibold">{formatPrice(FEE_CENTS, FEE_CURRENCY)}</span> applies.
              You&apos;ll be redirected to Paystack to complete payment.
            </p>

            <form onSubmit={handleSubmit} className="mt-4 space-y-3">
              <div>
                <label htmlFor="pay-name" className="block text-sm font-medium text-neutral-700">
                  Full name
                </label>
                <input
                  id="pay-name"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
                />
              </div>
              <div>
                <label htmlFor="pay-email" className="block text-sm font-medium text-neutral-700">
                  Email
                </label>
                <input
                  id="pay-email"
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
                />
                <p className="mt-1 text-xs text-neutral-500">Use this same email when you register in Step 2.</p>
              </div>

              {error && <p className="text-xs text-red-600">{error}</p>}

              <button
                type="submit"
                disabled={pending}
                className="w-full rounded-md bg-[var(--brand-accent)] px-4 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
              >
                {pending ? 'Redirecting to payment…' : `Pay ${formatPrice(FEE_CENTS, FEE_CURRENCY)}`}
              </button>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
