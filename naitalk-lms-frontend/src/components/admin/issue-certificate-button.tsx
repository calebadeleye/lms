'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function IssueCertificateButton({ enrolmentId }: { enrolmentId: number }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [issued, setIssued] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function issue() {
    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/admin/enrolments/${enrolmentId}/issue-certificate`, { method: 'POST' });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.course?.[0] ?? body?.errors?.[0]?.message ?? 'Could not issue certificate.');
        return;
      }

      setIssued(true);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (issued) {
    return <span className="text-xs font-medium text-green-600">Issued</span>;
  }

  return (
    <div>
      <button onClick={issue} disabled={pending} className="text-xs font-medium text-[var(--tenant-primary)] hover:underline">
        {pending ? 'Issuing…' : 'Issue certificate'}
      </button>
      {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
  );
}
