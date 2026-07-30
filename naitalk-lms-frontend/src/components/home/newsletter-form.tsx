'use client';

import { useState } from 'react';

/** Cosmetic only, by design — no backend/table exists for this yet. Shows a
 * confirmation so submitting doesn't feel silently broken, but nothing is
 * persisted anywhere. */
export function NewsletterForm() {
  const [email, setEmail] = useState('');
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim()) return;
    setSubmitted(true);
  }

  if (submitted) {
    return <p className="text-sm text-white/90">Thanks — we&apos;ll be in touch!</p>;
  }

  return (
    <form onSubmit={handleSubmit} className="flex gap-2">
      <input
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Enter your email"
        className="min-w-0 flex-1 rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/50 focus:border-[var(--brand-accent)] focus:outline-none"
      />
      <button
        type="submit"
        className="shrink-0 rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90"
      >
        Subscribe
      </button>
    </form>
  );
}
