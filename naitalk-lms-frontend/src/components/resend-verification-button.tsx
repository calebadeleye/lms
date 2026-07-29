'use client';

import { useState } from 'react';

export function ResendVerificationButton({ className }: { className?: string }) {
  const [pending, setPending] = useState(false);
  const [sent, setSent] = useState(false);

  async function resend() {
    setPending(true);
    try {
      await fetch('/api/v1/auth/email/resend', { method: 'POST' });
      setSent(true);
    } finally {
      setPending(false);
    }
  }

  if (sent) {
    return <p className="text-sm font-medium text-green-600">Verification email resent — check your inbox.</p>;
  }

  return (
    <button
      onClick={resend}
      disabled={pending}
      className={
        className ??
        'rounded-md bg-[var(--tenant-accent)] px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60'
      }
    >
      {pending ? 'Sending…' : 'Resend verification email'}
    </button>
  );
}
