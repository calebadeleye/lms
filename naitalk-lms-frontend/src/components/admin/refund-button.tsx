'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function RefundButton({ paymentId, maxAmountCents }: { paymentId: number; maxAmountCents: number }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [amountNaira, setAmountNaira] = useState('');
  const [reason, setReason] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/admin/payments/${paymentId}/refund`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount_cents: amountNaira ? Math.round(Number(amountNaira) * 100) : undefined,
          reason: reason || undefined,
        }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.amount_cents?.[0] ?? body?.errors?.[0]?.message ?? 'Could not process the refund.');
        return;
      }

      setOpen(false);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (!open) {
    return (
      <button onClick={() => setOpen(true)} className="text-xs font-medium text-red-600 hover:underline">
        Refund
      </button>
    );
  }

  return (
    <form onSubmit={submit} className="flex items-center gap-2">
      <input
        type="number"
        min="0"
        max={maxAmountCents / 100}
        step="0.01"
        value={amountNaira}
        onChange={(e) => setAmountNaira(e.target.value)}
        placeholder={`Full (₦${(maxAmountCents / 100).toLocaleString()})`}
        className="w-28 rounded-md border border-neutral-300 px-2 py-1 text-xs focus:border-[var(--tenant-primary)] focus:outline-none"
      />
      <input
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        placeholder="Reason (optional)"
        className="w-32 rounded-md border border-neutral-300 px-2 py-1 text-xs focus:border-[var(--tenant-primary)] focus:outline-none"
      />
      <button
        type="submit"
        disabled={pending}
        className="rounded-md bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Refunding…' : 'Confirm'}
      </button>
      <button type="button" onClick={() => setOpen(false)} className="text-xs text-neutral-500">
        Cancel
      </button>
      {error && <p className="text-xs text-red-600">{error}</p>}
    </form>
  );
}
