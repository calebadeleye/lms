'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function CertificateRevokeButton({ certificateId }: { certificateId: number }) {
  const router = useRouter();
  const [showForm, setShowForm] = useState(false);
  const [reason, setReason] = useState('');
  const [pending, setPending] = useState(false);

  async function revoke(e: React.FormEvent) {
    e.preventDefault();
    if (!reason.trim()) return;
    setPending(true);
    try {
      await fetch(`/api/v1/admin/certificates/${certificateId}/revoke`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason }),
      });
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (!showForm) {
    return (
      <button onClick={() => setShowForm(true)} className="text-xs font-semibold text-red-600 hover:underline">
        Revoke
      </button>
    );
  }

  return (
    <form onSubmit={revoke} className="flex items-center gap-2">
      <input
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        placeholder="Reason"
        className="w-32 rounded-md border border-neutral-300 px-2 py-1 text-xs focus:border-[var(--brand-primary)] focus:outline-none"
      />
      <button
        type="submit"
        disabled={pending || !reason.trim()}
        className="text-xs font-semibold text-red-600 hover:underline disabled:opacity-60"
      >
        {pending ? 'Revoking…' : 'Confirm'}
      </button>
    </form>
  );
}
