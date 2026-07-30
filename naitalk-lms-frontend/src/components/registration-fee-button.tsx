'use client';

import { useState } from 'react';

export function RegistrationFeeButton({ className }: { className?: string }) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function startCheckout() {
    setPending(true);
    setError(null);

    try {
      const callback_url = `${window.location.origin}/checkout/callback`;
      const res = await fetch('/api/v1/checkout/registration-fee', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ callback_url }),
      });

      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.payment?.[0] ?? 'Could not start checkout. Please try again.');
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
          'w-full rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60'
        }
      >
        {pending ? 'Redirecting…' : 'Pay registration fee'}
      </button>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
