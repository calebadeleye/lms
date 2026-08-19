'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { formatPrice } from '@/lib/learning-types';

const POLL_INTERVAL_MS = 2500;
const MAX_POLLS = 12; // ~30s

type FeeStatus = { status: 'pending' | 'paid' | 'failed'; amount_cents: number; currency: string; email: string };

/** Shown on the public membership page once Paystack redirects back with a
 * `reference`/`trxref` for a membership-fee payment (see
 * MembershipPayModal). Not shown for any other kind of checkout reference. */
export function MembershipFeeStatus({ reference }: { reference: string }) {
  const [fee, setFee] = useState<FeeStatus | null>(null);
  const [attempts, setAttempts] = useState(0);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function poll() {
      const res = await fetch(`/api/v1/checkout/membership-fee/status?reference=${encodeURIComponent(reference)}`);
      if (cancelled) return;

      if (!res.ok) {
        setNotFound(true);
        return;
      }

      const body = await res.json();
      setFee(body.data);
    }

    poll();
    return () => {
      cancelled = true;
    };
  }, [reference, attempts]);

  useEffect(() => {
    if (!fee || fee.status !== 'pending' || attempts >= MAX_POLLS) return;
    const timer = setTimeout(() => setAttempts((a) => a + 1), POLL_INTERVAL_MS);
    return () => clearTimeout(timer);
  }, [fee, attempts]);

  if (notFound) {
    return (
      <StatusCard icon="⚠️" title="We couldn't find that payment" message="If you completed a payment, please contact us." />
    );
  }

  if (!fee) {
    return <StatusCard icon="⏳" title="Checking your payment…" message="This will just take a moment." />;
  }

  if (fee.status === 'paid') {
    return (
      <StatusCard
        icon="✅"
        title="Payment successful"
        message={`You paid ${formatPrice(fee.amount_cents, fee.currency)}. Now complete Step 2 below — register using ${fee.email}, the same email you paid with.`}
        action={{ href: '/register', label: 'Continue to Step 2 — Register' }}
      />
    );
  }

  if (fee.status === 'pending') {
    if (attempts >= MAX_POLLS) {
      return (
        <StatusCard
          icon="⏳"
          title="Still confirming your payment"
          message="This is taking longer than usual. It will finish automatically — refresh this page shortly."
        />
      );
    }

    return <StatusCard icon="⏳" title="Confirming your payment…" message="Please don't close this page." />;
  }

  return (
    <StatusCard
      icon="❌"
      title="Payment not completed"
      message="Your payment could not be confirmed. No charge should have gone through — please try again below."
    />
  );
}

function StatusCard({
  icon,
  title,
  message,
  action,
}: {
  icon: string;
  title: string;
  message: string;
  action?: { href: string; label: string };
}) {
  return (
    <div className="mx-auto max-w-md rounded-xl border border-neutral-200 bg-white p-8 text-center">
      <div className="text-4xl">{icon}</div>
      <h2 className="mt-4 text-lg font-bold text-neutral-900">{title}</h2>
      <p className="mt-2 text-sm text-neutral-600">{message}</p>
      {action && (
        <Link
          href={action.href}
          className="mt-6 inline-block rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90"
        >
          {action.label}
        </Link>
      )}
    </div>
  );
}
