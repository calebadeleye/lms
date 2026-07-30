'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

type Errors = Record<string, string[]>;

const REQUIREMENTS = [
  {
    key: 'ack_impact_beyond_earning' as const,
    label: 'I’m passionate about making an impact beyond just working or earning a living.',
  },
  {
    key: 'ack_growth_mindset' as const,
    label: 'I have an open, growth mindset — flexible and willing to transform myself, learn, and grow others.',
  },
  {
    key: 'ack_interest_in_coaching' as const,
    label: 'I have a genuine interest in learning coaching and other related skills.',
  },
  {
    key: 'ack_positive_impact' as const,
    label: 'I’m passionate about making a positive impact on the world in one way or another.',
  },
];

type Step = 'requirements' | 'account' | 'welcome';

export function RegisterForm() {
  const router = useRouter();
  const [step, setStep] = useState<Step>('requirements');

  const [acks, setAcks] = useState<Record<string, boolean>>({});
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [motivation, setMotivation] = useState('');
  const [photo, setPhoto] = useState<File | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);

  const [errors, setErrors] = useState<Errors>({});
  const [pending, setPending] = useState(false);

  const allAcknowledged = REQUIREMENTS.every((r) => acks[r.key]);

  function handlePhoto(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    setPhoto(file);
    setPhotoPreview((prev) => {
      if (prev) URL.revokeObjectURL(prev);
      return file ? URL.createObjectURL(file) : null;
    });
  }

  function goToAccount(e: React.FormEvent) {
    e.preventDefault();
    if (!allAcknowledged) return;
    setStep('account');
  }

  function goToWelcome(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    if (password !== passwordConfirmation) {
      setErrors({ password_confirmation: ['Passwords do not match'] });
      return;
    }
    setStep('welcome');
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors({});

    try {
      const formData = new FormData();
      formData.append('name', name);
      formData.append('email', email);
      formData.append('password', password);
      formData.append('password_confirmation', passwordConfirmation);
      for (const r of REQUIREMENTS) {
        formData.append(r.key, acks[r.key] ? '1' : '0');
      }
      if (motivation.trim()) formData.append('motivation', motivation.trim());
      if (photo) formData.append('photo', photo);

      const res = await fetch('/api/auth/register', { method: 'POST', body: formData });
      const body = await res.json();

      if (!res.ok) {
        setErrors(body.errors ?? { email: ['Something went wrong. Please try again.'] });
        // Validation failures for acknowledgement/account fields mean those
        // earlier steps need another look, not the welcome step we're on.
        if (body.errors && REQUIREMENTS.some((r) => body.errors[r.key])) {
          setStep('requirements');
        } else if (body.errors && ('name' in body.errors || 'email' in body.errors || 'password' in body.errors)) {
          setStep('account');
        }
        return;
      }

      // The account and application now exist (status: pending) — the
      // registration fee is what actually finishes the submission, so we
      // go straight to Paystack rather than a "you're done" screen.
      const callback_url = `${window.location.origin}/checkout/callback`;
      const feeRes = await fetch('/api/v1/checkout/registration-fee', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ callback_url }),
      });
      const feeBody = await feeRes.json().catch(() => null);

      if (feeRes.ok && feeBody?.data?.authorization_url) {
        window.location.href = feeBody.data.authorization_url;
        return;
      }

      // Account was created either way — let them retry payment from the
      // pending page rather than stranding them on this form.
      router.push('/onboarding/pending');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="space-y-4">
      <ol className="flex items-center justify-center gap-2 text-xs font-medium text-neutral-400">
        {(['requirements', 'account', 'welcome'] as Step[]).map((s, i) => (
          <li key={s} className={`flex items-center gap-2 ${step === s ? 'text-[var(--brand-primary)]' : ''}`}>
            <span
              className={`grid h-5 w-5 place-items-center rounded-full border text-[10px] ${
                step === s ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white' : 'border-neutral-300'
              }`}
            >
              {i + 1}
            </span>
            {i < 2 && <span className="h-px w-6 bg-neutral-200" />}
          </li>
        ))}
      </ol>

      {step === 'requirements' && (
        <form onSubmit={goToAccount} className="space-y-4">
          <div>
            <h2 className="text-sm font-semibold text-neutral-900">Before you join</h2>
            <p className="mt-1 text-sm text-neutral-500">
              We&apos;re a community of professionals learning transformational skills as Change
              Agents in the Work of Now. Please confirm the following applies to you.
            </p>
          </div>
          <div className="space-y-3">
            {REQUIREMENTS.map((r) => (
              <label key={r.key} className="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 text-sm">
                <input
                  type="checkbox"
                  checked={acks[r.key] ?? false}
                  onChange={(e) => setAcks((prev) => ({ ...prev, [r.key]: e.target.checked }))}
                  className="mt-0.5 h-4 w-4 shrink-0 rounded border-neutral-300"
                />
                <span className="text-neutral-700">{r.label}</span>
              </label>
            ))}
          </div>
          <button
            type="submit"
            disabled={!allAcknowledged}
            className="w-full rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-40"
          >
            Continue
          </button>
        </form>
      )}

      {step === 'account' && (
        <form onSubmit={goToWelcome} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-neutral-700">
                Full name
              </label>
              <input
                id="name"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
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
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
              {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email[0]}</p>}
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
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
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
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
              {errors.password_confirmation && (
                <p className="mt-1 text-xs text-red-600">{errors.password_confirmation[0]}</p>
              )}
            </div>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setStep('requirements')}
              className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
            >
              Back
            </button>
            <button
              type="submit"
              className="flex-1 rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90"
            >
              Continue
            </button>
          </div>
        </form>
      )}

      {step === 'welcome' && (
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <h2 className="text-sm font-semibold text-neutral-900">Your grand welcome</h2>
            <p className="mt-1 text-sm text-neutral-500">
              Share a photo for your welcome into the community, and tell us a little about why
              you&apos;re joining. Both are optional — you can add them later.
            </p>
          </div>

          <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
            <label className="cursor-pointer shrink-0">
              {photoPreview ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={photoPreview} alt="" className="h-20 w-20 rounded-full object-cover" />
              ) : (
                <div className="grid h-20 w-20 place-items-center rounded-full border-2 border-dashed border-neutral-300 bg-neutral-50 text-xs text-neutral-400 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">
                  Add photo
                </div>
              )}
              <input type="file" accept="image/png,image/jpeg,image/webp" onChange={handlePhoto} className="hidden" />
            </label>
            {errors.photo && <p className="text-xs text-red-600">{errors.photo[0]}</p>}

            <div className="flex-1">
              <label htmlFor="motivation" className="block text-sm font-medium text-neutral-700">
                Why are you joining? (optional)
              </label>
              <textarea
                id="motivation"
                rows={3}
                value={motivation}
                onChange={(e) => setMotivation(e.target.value)}
                maxLength={1000}
                className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
              />
            </div>
          </div>

          {errors.email && <p className="text-sm text-red-600">{errors.email[0]}</p>}

          <p className="rounded-md bg-[var(--brand-primary)]/5 p-3 text-xs text-neutral-600">
            A one-time registration fee of <span className="font-semibold">₦20,000</span>{' '}
            applies. After you submit, you&apos;ll be redirected to Paystack to complete payment before your application is
            reviewed.
          </p>

          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setStep('account')}
              className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
            >
              Back
            </button>
            <button
              type="submit"
              disabled={pending}
              className="flex-1 rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
            >
              {pending ? 'Redirecting to payment…' : 'Submit & pay ₦20,000'}
            </button>
          </div>
        </form>
      )}

      <p className="text-center text-sm text-neutral-500">
        Already have an account?{' '}
        <a href="/login" className="font-medium text-[var(--brand-primary)]">
          Sign in
        </a>
      </p>
    </div>
  );
}
