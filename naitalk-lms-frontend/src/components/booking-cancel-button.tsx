'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function BookingCancelButton({ bookingId }: { bookingId: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);

  async function cancel() {
    setPending(true);
    try {
      await fetch(`/api/v1/bookings/${bookingId}/cancel`, { method: 'POST' });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <button
      onClick={cancel}
      disabled={pending}
      className="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60"
    >
      {pending ? 'Cancelling…' : 'Cancel'}
    </button>
  );
}
