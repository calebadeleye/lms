'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { CheckoutButton } from '@/components/checkout-button';

export function EnrollButton({
  courseId,
  isFree,
  isMembershipOnly,
  isEnrolled,
  isAuthenticated,
}: {
  courseId: number;
  isFree: boolean;
  isMembershipOnly?: boolean;
  isEnrolled: boolean;
  isAuthenticated: boolean;
}) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (isEnrolled) {
    return (
      <button
        onClick={() => router.push('/my/courses')}
        className="w-full rounded-md bg-[var(--tenant-accent)] px-6 py-3 text-sm font-semibold text-white hover:opacity-90"
      >
        Continue Learning
      </button>
    );
  }

  if (!isFree && !isMembershipOnly) {
    if (!isAuthenticated) {
      return (
        <button
          onClick={() => router.push(`/login?redirect=/courses`)}
          className="w-full rounded-md bg-[var(--tenant-accent)] px-6 py-3 text-sm font-semibold text-white hover:opacity-90"
        >
          Log in to Purchase
        </button>
      );
    }

    return <CheckoutButton kind="courses" id={courseId} label="Buy Now" pendingLabel="Redirecting to payment…" />;
  }

  // Free courses enrol directly. Membership-only courses go through the
  // same endpoint — the backend is the source of truth on whether the
  // caller's membership actually unlocks it, so a member gets enrolled
  // immediately and a non-member gets the real reason back as a 422.
  async function handleEnroll() {
    if (!isAuthenticated) {
      router.push(`/login?redirect=/courses`);
      return;
    }

    setPending(true);
    setError(null);

    try {
      const res = await fetch(`/api/v1/courses/${courseId}/enrol`, { method: 'POST' });

      if (!res.ok) {
        const body = await res.json().catch(() => null);
        setError(body?.errors?.course?.[0] ?? 'Could not enrol. Please try again.');
        return;
      }

      router.push('/my/courses');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div>
      <button
        onClick={handleEnroll}
        disabled={pending}
        className="w-full rounded-md bg-[var(--tenant-accent)] px-6 py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Enrolling…' : isMembershipOnly ? 'Enrol with Membership' : 'Enrol Now — Free'}
      </button>
      {error && (
        <p className="mt-2 text-xs text-red-600">
          {error}
          {isMembershipOnly && (
            <>
              {' '}
              <Link href="/membership" className="font-medium underline">
                View membership plans
              </Link>
              .
            </>
          )}
        </p>
      )}
    </div>
  );
}
