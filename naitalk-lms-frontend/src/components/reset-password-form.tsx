'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const router = useRouter();
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, setPending] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors({});

    try {
      const res = await fetch('/api/auth/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, email, password, password_confirmation: passwordConfirmation }),
      });
      const body = await res.json();

      if (!res.ok) {
        setErrors(body.errors ?? { email: ['This reset link is invalid or has expired.'] });
        return;
      }

      router.push('/login');
    } finally {
      setPending(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label htmlFor="password" className="block text-sm font-medium text-neutral-700">
          New password
        </label>
        <input
          id="password"
          type="password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password[0]}</p>}
      </div>
      <div>
        <label htmlFor="password_confirmation" className="block text-sm font-medium text-neutral-700">
          Confirm new password
        </label>
        <input
          id="password_confirmation"
          type="password"
          required
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
        {errors.password_confirmation && (
          <p className="mt-1 text-xs text-red-600">{errors.password_confirmation[0]}</p>
        )}
      </div>
      {errors.email && <p className="text-sm text-red-600">{errors.email[0]}</p>}
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Resetting…' : 'Reset password'}
      </button>
    </form>
  );
}
