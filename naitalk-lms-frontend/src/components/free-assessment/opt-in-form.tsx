'use client';

import { useState } from 'react';
import Link from 'next/link';

type Result = { kind: 'sent'; email: string } | { kind: 'link'; url: string };

/**
 * Free personality assessment opt-in: name + email, and the backend emails
 * the assessment link (AssessmentLeadController). If the email can't be
 * sent, the backend returns the link and we show it here instead, so a
 * visitor is never left empty-handed.
 */
export function AssessmentOptInForm({ source }: { source: 'landing' | 'home' }) {
  const [fullName, setFullName] = useState('');
  const [email, setEmail] = useState('');
  const [website, setWebsite] = useState(''); // honeypot — hidden from people
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [result, setResult] = useState<Result | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setErrors({});

    try {
      const res = await fetch('/api/v1/assessment-leads', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ full_name: fullName, email, source, website }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setErrors(body?.errors ?? { email: ['Something went wrong. Please try again.'] });
        return;
      }

      setResult(body?.data?.email_sent === false && body.data.assessment_url
        ? { kind: 'link', url: body.data.assessment_url }
        : { kind: 'sent', email });
    } catch {
      setErrors({ email: ['Something went wrong. Please check your connection and try again.'] });
    } finally {
      setPending(false);
    }
  }

  if (result?.kind === 'sent') {
    return (
      <div role="status" className="text-center">
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#f4b728]/20 text-2xl" aria-hidden>
          📩
        </div>
        <h3 className="mt-4 text-xl font-extrabold text-[#073d40]">Check your inbox!</h3>
        <p className="mt-2 text-sm leading-6 text-[#3f4c4e]">
          We&apos;ve sent your free assessment to <span className="font-bold text-[#073d40]">{result.email}</span>.
          Can&apos;t find it? Check your spam or promotions folder.
        </p>
        <Link href="/courses/find-your-career-fit" className="mt-5 inline-flex text-sm font-bold text-[#006c70] underline">
          Ready for more? See the Find Your Career Fit course →
        </Link>
      </div>
    );
  }

  if (result?.kind === 'link') {
    return (
      <div role="status" className="text-center">
        <h3 className="text-xl font-extrabold text-[#073d40]">Your assessment is ready</h3>
        <p className="mt-2 text-sm leading-6 text-[#3f4c4e]">
          We couldn&apos;t email you just now, so here&apos;s your link. Start whenever you&apos;re ready.
        </p>
        <a
          href={result.url}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#073d40] px-6 text-[15px] font-bold text-white hover:bg-[#0a5257]"
        >
          Take your free assessment <span aria-hidden>→</span>
        </a>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="space-y-3" noValidate>
      <Field
        id={`fa-name-${source}`}
        label="Full Name"
        icon={<PersonIcon />}
        value={fullName}
        onChange={setFullName}
        autoComplete="name"
        error={errors.full_name?.[0]}
      />
      <Field
        id={`fa-email-${source}`}
        label="Email Address"
        type="email"
        icon={<MailIcon />}
        value={email}
        onChange={setEmail}
        autoComplete="email"
        error={errors.email?.[0]}
      />

      {/* Honeypot: off-screen and skipped by keyboard and screen readers. */}
      <div aria-hidden className="absolute -left-[9999px] h-px w-px overflow-hidden">
        <label>
          Website
          <input tabIndex={-1} autoComplete="off" value={website} onChange={(e) => setWebsite(e.target.value)} />
        </label>
      </div>

      <button
        type="submit"
        disabled={pending}
        className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#073d40] px-6 text-[15px] font-bold text-white transition hover:bg-[#0a5257] disabled:opacity-60"
      >
        {pending ? 'Sending…' : (<>Get My Free Assessment <span aria-hidden>→</span></>)}
      </button>
      <p className="flex items-center justify-center gap-1.5 text-xs text-[#5b6a6c]">
        <span aria-hidden>🔒</span> Your details are safe with us. No payment required.
      </p>
    </form>
  );
}

function Field({
  id,
  label,
  icon,
  value,
  onChange,
  type = 'text',
  autoComplete,
  error,
}: {
  id: string;
  label: string;
  icon: React.ReactNode;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  autoComplete?: string;
  error?: string;
}) {
  return (
    <div>
      <label htmlFor={id} className="sr-only">{label}</label>
      <div className={`flex items-center gap-3 rounded-xl border bg-white px-4 focus-within:border-[#006c70] focus-within:ring-2 focus-within:ring-[#006c70]/15 ${error ? 'border-red-400' : 'border-[#cfd8d6]'}`}>
        <span className="text-[#6b7a7c]" aria-hidden>{icon}</span>
        <input
          id={id}
          type={type}
          required
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={label}
          autoComplete={autoComplete}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${id}-error` : undefined}
          className="min-h-12 w-full bg-transparent text-[15px] text-[#172326] placeholder:text-[#6b7a7c] focus:outline-none"
        />
      </div>
      {error && <p id={`${id}-error`} className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
  );
}

function PersonIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.7">
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" strokeLinecap="round" />
    </svg>
  );
}

function MailIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.7">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m4 7 8 6 8-6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
