'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

type Errors = Record<string, string[]>;

/**
 * Learner sign-up: just the account fields, free, and logs you in. Not to
 * be confused with RegisterForm (the paid, reviewed Coach Network
 * application) — the two are deliberately separate flows.
 */
export function SignupForm({ redirectTo }: { redirectTo: string }) {
  const router = useRouter();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [errors, setErrors] = useState<Errors>({});
  const [pending, setPending] = useState(false);

  const query = redirectTo ? `?redirect=${encodeURIComponent(redirectTo)}` : '';

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});

    if (password !== passwordConfirmation) {
      setErrors({ password_confirmation: ['Passwords do not match'] });
      return;
    }

    setPending(true);

    try {
      const res = await fetch('/api/auth/signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password, password_confirmation: passwordConfirmation }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setErrors(body?.errors ?? { email: ['Something went wrong. Please try again.'] });
        return;
      }

      // The new account has to confirm its email before it can buy
      // anything (the `verified` middleware), so land on the "check your
      // inbox" page — the course they came for is one click away after.
      router.push('/verify-email');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  const inputClass =
    'mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none';

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label htmlFor="name" className="block text-sm font-medium text-neutral-700">
          Full name
        </label>
        <input id="name" required value={name} onChange={(e) => setName(e.target.value)} className={inputClass} />
        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name[0]}</p>}
      </div>
      <div>
        <label htmlFor="email" className="block text-sm font-medium text-neutral-700">
          Email
        </label>
        <input
          id="email"
          type="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className={inputClass}
        />
        {errors.email && (
          <p className="mt-1 text-xs text-red-600">
            {errors.email[0]}
            {errors.email[0]?.toLowerCase().includes('already exists') && (
              <>
                {' '}
                <a href={`/login${query}`} className="font-medium underline">
                  Log in instead
                </a>
                .
              </>
            )}
          </p>
        )}
      </div>
      <div>
        <label htmlFor="password" className="block text-sm font-medium text-neutral-700">
          Password
        </label>
        <input
          id="password"
          type="password"
          required
          minLength={10}
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className={inputClass}
        />
        <p className="mt-1 text-xs text-neutral-400">At least 10 characters, with upper and lower case letters and a number.</p>
        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password[0]}</p>}
      </div>
      <div>
        <label htmlFor="password_confirmation" className="block text-sm font-medium text-neutral-700">
          Confirm password
        </label>
        <input
          id="password_confirmation"
          type="password"
          required
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          className={inputClass}
        />
        {errors.password_confirmation && (
          <p className="mt-1 text-xs text-red-600">{errors.password_confirmation[0]}</p>
        )}
      </div>
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Creating account…' : 'Create account'}
      </button>
      <p className="text-center text-sm text-neutral-500">
        Already have an account?{' '}
        <a href={`/login${query}`} className="font-medium text-[var(--brand-primary)]">
          Sign in
        </a>
      </p>
      <p className="border-t border-neutral-100 pt-4 text-center text-xs text-neutral-500">
        Looking to become a coach or join our community?{' '}
        <a href="/register" className="font-medium text-[var(--brand-primary)]">
          Join the HR GEMs Coach Network
        </a>
      </p>
    </form>
  );
}
