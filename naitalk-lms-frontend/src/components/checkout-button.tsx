'use client';

import { useState } from 'react';

type CheckoutKind = 'courses' | 'membership-plans' | 'coaching-bookings';

export function CheckoutButton({
  kind,
  id,
  label,
  pendingLabel,
  className,
}: {
  kind: CheckoutKind;
  id: number;
  label: string;
  pendingLabel?: string;
  className?: string;
}) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function startCheckout() {
    setPending(true);
    setError(null);

    try {
      const callback_url = `${window.location.origin}/checkout/callback`;
      const res = await fetch(`/api/v1/checkout/${kind}/${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ callback_url }),
      });

      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.payment?.[0] ?? body?.errors?.[0]?.message ?? 'Could not start checkout. Please try again.');
        setPending(false);
        return;
      }

      window.location.href = body.data.authorization_url;
    } catch {
      setError('Could not start checkout. Please try again.');
      setPending(false);
    }
  }

  return (
    <div>
      <button
        onClick={startCheckout}
        disabled={pending}
        className={
          className ??
          'w-full rounded-md bg-[var(--tenant-accent)] px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60'
        }
      >
        {pending ? (pendingLabel ?? 'Redirecting…') : label}
      </button>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
