'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export interface MembershipApplication {
  id: number;
  user: { id: number; name: string; email: string; email_verified_at: string | null };
  status: 'pending' | 'approved' | 'rejected';
  has_photo: boolean;
  submitted_at: string;
}

interface ApplicationDetail extends MembershipApplication {
  ack_impact_beyond_earning: boolean;
  ack_growth_mindset: boolean;
  ack_interest_in_coaching: boolean;
  ack_positive_impact: boolean;
  motivation: string | null;
  photo_url: string | null;
  reviewed_by: { id: number; name: string } | null;
  reviewed_at: string | null;
  review_note: string | null;
}

/** `toLocaleDateString()` with no explicit locale defers to the runtime's
 * default, which differs between the Node.js SSR pass and the browser —
 * causing a hydration mismatch. Pinning a locale keeps server and client
 * output identical. */
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

const ACK_LABELS: [keyof ApplicationDetail, string][] = [
  ['ack_impact_beyond_earning', 'Passionate about impact beyond earning a living'],
  ['ack_growth_mindset', 'Open, growth mindset'],
  ['ack_interest_in_coaching', 'Genuine interest in coaching'],
  ['ack_positive_impact', 'Passionate about positive impact'],
];

export function ApplicationsQueue({ initial }: { initial: MembershipApplication[] }) {
  const router = useRouter();
  const [applications, setApplications] = useState(initial);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<ApplicationDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [note, setNote] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function toggleExpand(application: MembershipApplication) {
    if (expandedId === application.id) {
      setExpandedId(null);
      setDetail(null);
      return;
    }

    setExpandedId(application.id);
    setDetail(null);
    setNote('');
    setError(null);
    setDetailLoading(true);
    try {
      const res = await fetch(`/api/v1/admin/applications/${application.id}`);
      const body = await res.json();
      if (res.ok) setDetail(body.data);
    } finally {
      setDetailLoading(false);
    }
  }

  async function review(application: MembershipApplication, action: 'approve' | 'reject') {
    if (action === 'reject' && !note.trim()) {
      setError('A note is required when rejecting an application.');
      return;
    }

    setPending(true);
    setError(null);
    try {
      const res = await fetch(`/api/v1/admin/applications/${application.id}/${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(note.trim() ? { note: note.trim() } : {}),
      });
      const body = await res.json();

      if (!res.ok) {
        setError(body?.errors?.[0]?.message ?? body?.errors?.note?.[0] ?? 'Could not process the application.');
        return;
      }

      setApplications((prev) => prev.filter((a) => a.id !== application.id));
      setExpandedId(null);
      setDetail(null);
      setNote('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  if (applications.length === 0) {
    return <p className="rounded-xl border border-neutral-200 bg-white p-6 text-sm text-neutral-500">No pending applications right now.</p>;
  }

  return (
    <ul className="space-y-3">
      {applications.map((application) => (
        <li key={application.id} className="rounded-xl border border-neutral-200 bg-white p-4">
          <button onClick={() => toggleExpand(application)} className="flex w-full items-center justify-between text-left">
            <div>
              <p className="text-sm font-semibold text-neutral-900">{application.user.name}</p>
              <p className="text-xs text-neutral-500">
                {application.user.email}
                {!application.user.email_verified_at && (
                  <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                    Email not verified
                  </span>
                )}
                {!application.has_photo && (
                  <span className="ml-2 rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-500">No photo</span>
                )}
              </p>
            </div>
            <span className="text-xs text-neutral-400">{formatDate(application.submitted_at)}</span>
          </button>

          {expandedId === application.id && (
            <div className="mt-4 space-y-4 border-t border-neutral-100 pt-4">
              {detailLoading && <p className="text-sm text-neutral-400">Loading…</p>}

              {detail && (
                <>
                  <div className="flex items-start gap-4">
                    {detail.photo_url ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={detail.photo_url} alt="" className="h-16 w-16 rounded-full object-cover" />
                    ) : (
                      <div className="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-neutral-100 text-xs text-neutral-400">
                        No photo
                      </div>
                    )}
                    <ul className="flex-1 space-y-1 text-sm">
                      {ACK_LABELS.map(([key, label]) => (
                        <li key={key} className="flex items-center gap-2 text-neutral-700">
                          <span aria-hidden>{detail[key] ? '✅' : '❌'}</span>
                          {label}
                        </li>
                      ))}
                    </ul>
                  </div>

                  {detail.motivation && (
                    <div>
                      <p className="text-xs font-medium text-neutral-500">Why they&apos;re joining</p>
                      <p className="mt-1 text-sm italic text-neutral-700">&ldquo;{detail.motivation}&rdquo;</p>
                    </div>
                  )}

                  <div>
                    <label htmlFor={`note-${application.id}`} className="block text-xs font-medium text-neutral-500">
                      Note (required to reject, optional to approve)
                    </label>
                    <textarea
                      id={`note-${application.id}`}
                      rows={2}
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
                    />
                  </div>

                  {error && <p className="text-sm text-red-600">{error}</p>}

                  <div className="flex gap-2">
                    <button
                      onClick={() => review(application, 'approve')}
                      disabled={pending}
                      className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
                    >
                      Approve
                    </button>
                    <button
                      onClick={() => review(application, 'reject')}
                      disabled={pending}
                      className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-60"
                    >
                      Reject
                    </button>
                  </div>
                </>
              )}
            </div>
          )}
        </li>
      ))}
    </ul>
  );
}
