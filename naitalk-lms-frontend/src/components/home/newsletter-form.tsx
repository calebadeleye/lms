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

  if (submitted) return <p className="text-xs font-medium text-white/90">Thanks — we&apos;ll be in touch!</p>;

  return (
    <form onSubmit={handleSubmit} className="flex overflow-hidden rounded-md shadow-[0_8px_18px_rgba(0,0,0,0.18)]">
      <input
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="Enter your email"
        className="min-w-0 flex-1 border-0 bg-white px-4 py-3 text-xs text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
      />
      <button
        type="submit"
        className="shrink-0 bg-[#ffbd11] px-5 py-3 text-xs font-black text-neutral-950 hover:opacity-90"
      >
        Subscribe
      </button>
    </form>
  );
}
