'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export interface Testimonial {
  id: number;
  quote: string;
  author: string;
}

export function TestimonialManager({ initial }: { initial: Testimonial[] }) {
  const router = useRouter();
  const [testimonials, setTestimonials] = useState(initial);
  const [quote, setQuote] = useState('');
  const [author, setAuthor] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function addTestimonial(e: React.FormEvent) {
    e.preventDefault();
    if (!quote.trim() || !author.trim()) return;
    setPending(true);
    setError(null);

    try {
      const res = await fetch('/api/v1/admin/testimonials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quote, author }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.[0]?.message ?? 'Could not add testimonial.');
        return;
      }

      setTestimonials((prev) => [...prev, body.data]);
      setQuote('');
      setAuthor('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function remove(testimonial: Testimonial) {
    if (!window.confirm(`Delete this testimonial from ${testimonial.author}?`)) return;
    await fetch(`/api/v1/admin/testimonials/${testimonial.id}`, { method: 'DELETE' });
    setTestimonials((prev) => prev.filter((t) => t.id !== testimonial.id));
    router.refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-6">
      <h2 className="text-sm font-semibold text-neutral-900">Testimonials</h2>
      <p className="mt-1 text-xs text-neutral-500">Shown on your public homepage.</p>

      <ul className="mt-4 space-y-3">
        {testimonials.map((testimonial) => (
          <li key={testimonial.id} className="rounded-lg border border-neutral-200 p-3">
            <p className="text-sm italic text-neutral-700">&ldquo;{testimonial.quote}&rdquo;</p>
            <div className="mt-2 flex items-center justify-between">
              <p className="text-xs text-neutral-500">&mdash; {testimonial.author}</p>
              <button onClick={() => remove(testimonial)} className="text-xs font-medium text-red-600 hover:underline">
                Delete
              </button>
            </div>
          </li>
        ))}
        {testimonials.length === 0 && <li className="text-sm text-neutral-500">No testimonials yet.</li>}
      </ul>

      <form onSubmit={addTestimonial} className="mt-5 space-y-3 border-t border-neutral-200 pt-5">
        <div>
          <label htmlFor="quote" className="block text-sm font-medium text-neutral-700">
            Quote
          </label>
          <textarea
            id="quote"
            value={quote}
            onChange={(e) => setQuote(e.target.value)}
            rows={3}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        <div>
          <label htmlFor="author" className="block text-sm font-medium text-neutral-700">
            Author
          </label>
          <input
            id="author"
            value={author}
            onChange={(e) => setAuthor(e.target.value)}
            placeholder="Funke A., HR Manager"
            className="mt-1 w-full max-w-sm rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          />
        </div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <button
          type="submit"
          disabled={pending || !quote.trim() || !author.trim()}
          className="rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Adding…' : 'Add testimonial'}
        </button>
      </form>
    </div>
  );
}
