'use client';

import { useEffect, useState } from 'react';

interface Submission {
  id: number;
  content_text: string | null;
  submitted_at: string;
  grade: number | null;
  feedback: string | null;
  graded_at: string | null;
}

interface AssignmentData {
  id: number;
  title: string;
  instructions: string | null;
  max_points: number;
  due_date: string | null;
  submission: Submission | null;
}

export function AssignmentPlayer({ lessonId }: { lessonId: number }) {
  const [assignment, setAssignment] = useState<AssignmentData | null>(null);
  const [text, setText] = useState('');
  const [loading, setLoading] = useState(true);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    fetch(`/api/v1/lessons/${lessonId}/assignment`)
      .then((res) => res.json())
      .then((body) => {
        setAssignment(body.data);
        setText(body.data?.submission?.content_text ?? '');
      })
      .finally(() => setLoading(false));
  }, [lessonId]);

  async function submit() {
    setPending(true);
    try {
      const res = await fetch(`/api/v1/lessons/${lessonId}/assignment/submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content_text: text }),
      });
      const body = await res.json();
      if (res.ok) {
        setAssignment((prev) => (prev ? { ...prev, submission: body.data } : prev));
      }
    } finally {
      setPending(false);
    }
  }

  if (loading) return <p className="text-sm text-neutral-500">Loading assignment…</p>;
  if (!assignment) return <p className="text-sm text-neutral-500">Assignment not found.</p>;

  const graded = assignment.submission?.graded_at;

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-6">
      <h3 className="font-semibold text-neutral-900">{assignment.title}</h3>
      <p className="mt-2 whitespace-pre-line text-sm text-neutral-600">{assignment.instructions}</p>
      <p className="mt-2 text-xs text-neutral-500">Max points: {assignment.max_points}</p>

      {graded && (
        <div className="mt-4 rounded-md bg-green-50 p-4">
          <p className="text-sm font-semibold text-green-800">
            Grade: {assignment.submission?.grade}/{assignment.max_points}
          </p>
          {assignment.submission?.feedback && (
            <p className="mt-1 text-sm text-green-700">{assignment.submission.feedback}</p>
          )}
        </div>
      )}

      <div className="mt-4">
        <label className="block text-sm font-medium text-neutral-700">Your submission</label>
        <textarea
          rows={6}
          value={text}
          onChange={(e) => setText(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
        />
        {assignment.submission && (
          <p className="mt-1 text-xs text-neutral-500">
            Last submitted {new Date(assignment.submission.submitted_at).toLocaleString()}
          </p>
        )}
        <button
          onClick={submit}
          disabled={pending || !text.trim()}
          className="mt-3 rounded-md bg-[var(--tenant-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Submitting…' : assignment.submission ? 'Resubmit' : 'Submit'}
        </button>
      </div>
    </div>
  );
}
