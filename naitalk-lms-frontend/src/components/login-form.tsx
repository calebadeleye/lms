'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

type Errors = Record<string, string[]>;

export function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [code, setCode] = useState('');
  const [errors, setErrors] = useState<Errors>({});
  const [pending, setPending] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors({});

    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const body = await res.json();

      if (!res.ok) {
        setErrors(body.errors ?? { email: ['Something went wrong. Please try again.'] });
        return;
      }

      if (body.data?.mfa_required) {
        setMfaToken(body.data.mfa_token);
        return;
      }

      router.push('/dashboard');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function handleMfaSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors({});

    try {
      const res = await fetch('/api/auth/mfa/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mfa_token: mfaToken, code }),
      });
      const body = await res.json();

      if (!res.ok) {
        setErrors(body.errors ?? { code: ['Invalid code.'] });
        return;
      }

      router.push('/dashboard');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (mfaToken) {
    return (
      <form onSubmit={handleMfaSubmit} className="space-y-4">
        <div>
          <label htmlFor="code" className="block text-sm font-medium text-neutral-700">
            Authentication code
          </label>
          <input
            id="code"
            inputMode="numeric"
            autoFocus
            value={code}
            onChange={(e) => setCode(e.target.value)}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
          {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code[0]}</p>}
        </div>
        <button
          type="submit"
          disabled={pending}
          className="w-full rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Verifying…' : 'Verify'}
        </button>
      </form>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
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
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
      </div>
      <div>
        <div className="flex items-center justify-between">
          <label htmlFor="password" className="block text-sm font-medium text-neutral-700">
            Password
          </label>
          <a href="/forgot-password" className="text-xs font-medium text-[var(--tenant-primary)]">
            Forgot password?
          </a>
        </div>
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
      <button
        type="submit"
        disabled={pending}
        className="w-full rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
      >
        {pending ? 'Signing in…' : 'Sign In'}
      </button>
      <p className="text-center text-sm text-neutral-500">
        Don&apos;t have an account?{' '}
        <a href="/register" className="font-medium text-[var(--tenant-primary)]">
          Sign up
        </a>
      </p>
    </form>
  );
}
