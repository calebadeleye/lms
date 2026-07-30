'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

const RESULT_MESSAGES: Record<string, string> = {
  fulfilled: 'Payment confirmed — access has been unlocked.',
  already_paid: 'This order was already paid.',
  not_paid: "The provider says this wasn't actually paid.",
  no_reference: 'No payment reference on this order to check.',
};

export function ReconcileButton({ orderId }: { orderId: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function checkStatus() {
    setPending(true);
    setMessage(null);

    try {
      const res = await fetch(`/api/v1/admin/orders/${orderId}/reconcile`, { method: 'POST' });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setMessage(body?.errors?.order?.[0] ?? 'Could not check payment status.');
        return;
      }

      setMessage(RESULT_MESSAGES[body.data.result] ?? 'Checked.');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <button
        onClick={checkStatus}
        disabled={pending}
        className="text-xs font-medium text-[var(--brand-primary)] hover:underline disabled:opacity-60"
      >
        {pending ? 'Checking…' : 'Check payment status'}
      </button>
      {message && <p className="text-xs text-neutral-500">{message}</p>}
    </div>
  );
}
