'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import type { OrderStatus } from '@/lib/commerce-types';
import { formatPrice } from '@/lib/learning-types';

const POLL_INTERVAL_MS = 2500;
const MAX_POLLS = 12; // ~30s — long enough for the webhook to usually land

export function CheckoutStatus({
  orderId,
  reference,
  isAuthenticated,
}: {
  orderId: number | null;
  reference: string;
  isAuthenticated: boolean;
}) {
  const [order, setOrder] = useState<OrderStatus | null>(null);
  const [attempts, setAttempts] = useState(0);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (!orderId) return;

    let cancelled = false;

    async function poll() {
      // No session yet on a first-time registration-fee payment (register()
      // deliberately doesn't log the applicant in) — use the public,
      // reference-scoped status check instead of the authenticated one.
      const url = isAuthenticated
        ? `/api/v1/checkout/orders/${orderId}`
        : `/api/v1/checkout/registration-fee/status?reference=${encodeURIComponent(reference)}`;
      const res = await fetch(url);
      if (cancelled) return;

      if (!res.ok) {
        setNotFound(true);
        return;
      }

      const body = await res.json();
      setOrder(body.data);
    }

    poll();
    return () => {
      cancelled = true;
    };
  }, [orderId, reference, isAuthenticated, attempts]);

  useEffect(() => {
    if (!order || order.status !== 'pending' || attempts >= MAX_POLLS) return;
    const timer = setTimeout(() => setAttempts((a) => a + 1), POLL_INTERVAL_MS);
    return () => clearTimeout(timer);
  }, [order, attempts]);

  if (!orderId || notFound) {
    return (
      <StatusCard
        icon="⚠️"
        title="We couldn't find that order"
        message="If you completed a payment, check My Orders in a moment — the confirmation may still be processing."
      />
    );
  }

  if (!order) {
    return <StatusCard icon="⏳" title="Checking your payment…" message="This will just take a moment." />;
  }

  if (order.status === 'paid') {
    const type = order.items[0]?.itemable_type;
    const isRegistrationFee = type === 'membership_application';

    return (
      <StatusCard
        icon="✅"
        title="Payment successful"
        message={
          isRegistrationFee && !isAuthenticated
            ? `You paid ${formatPrice(order.total_cents, order.currency)}. Log in to check your application's status.`
            : `You paid ${formatPrice(order.total_cents, order.currency)}. Access has been unlocked.`
        }
        action={{ href: itemDestination(order, isAuthenticated), label: isRegistrationFee && !isAuthenticated ? 'Log in' : 'Continue' }}
      />
    );
  }

  if (order.status === 'pending') {
    if (attempts >= MAX_POLLS) {
      return (
        <StatusCard
          icon="⏳"
          title="Still confirming your payment"
          message="This is taking longer than usual. It will finish automatically — check My Orders shortly."
          action={{ href: '/my/orders', label: 'Go to My Orders' }}
        />
      );
    }

    return <StatusCard icon="⏳" title="Confirming your payment…" message="Please don't close this page." />;
  }

  return (
    <StatusCard
      icon="❌"
      title="Payment not completed"
      message="Your payment could not be confirmed. No charge should have gone through — please try again."
      action={{ href: '/courses', label: 'Back to Courses' }}
    />
  );
}

function itemDestination(order: OrderStatus, isAuthenticated: boolean): string {
  const type = order.items[0]?.itemable_type;
  if (type === 'membership_plan') return '/membership';
  if (type === 'booking') return '/my/bookings';
  if (type === 'membership_application') return isAuthenticated ? '/onboarding/pending' : '/login';
  return '/my/courses';
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
      <h1 className="mt-4 text-lg font-bold text-neutral-900">{title}</h1>
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
