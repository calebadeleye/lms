'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

interface QuizOption {
  id: number;
  option_text: string;
}

interface QuizQuestion {
  id: number;
  type: 'multiple_choice' | 'multiple_answer' | 'true_false' | 'free_text';
  question_text: string;
  points: number;
  options: QuizOption[];
}

interface QuizData {
  id: number;
  passing_score_percent: number;
  max_attempts: number | null;
  attempts_used: number;
  can_attempt: boolean;
  questions: QuizQuestion[];
}

interface AttemptResult {
  id: number;
  score_percent: number;
  passed: boolean;
  submitted_at: string;
}

export function QuizPlayer({ lessonId }: { lessonId: number }) {
  const router = useRouter();
  const [quiz, setQuiz] = useState<QuizData | null>(null);
  const [attemptId, setAttemptId] = useState<number | null>(null);
  const [answers, setAnswers] = useState<Record<number, number[]>>({});
  const [freeTextAnswers, setFreeTextAnswers] = useState<Record<number, string>>({});
  const [result, setResult] = useState<AttemptResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch(`/api/v1/lessons/${lessonId}/quiz`)
      .then((res) => res.json())
      .then((body) => setQuiz(body.data))
      .finally(() => setLoading(false));
  }, [lessonId]);

  async function startAttempt() {
    setError(null);
    const res = await fetch(`/api/v1/lessons/${lessonId}/quiz/attempts`, { method: 'POST' });
    const body = await res.json();

    if (!res.ok) {
      setError(body?.errors?.quiz?.[0] ?? 'Could not start attempt.');
      return;
    }

    setAttemptId(body.data.id);
    setAnswers({});
    setFreeTextAnswers({});
  }

  function toggleOption(questionId: number, optionId: number, singleSelect: boolean) {
    setAnswers((prev) => {
      const current = prev[questionId] ?? [];
      if (singleSelect) return { ...prev, [questionId]: [optionId] };
      const next = current.includes(optionId) ? current.filter((id) => id !== optionId) : [...current, optionId];
      return { ...prev, [questionId]: next };
    });
  }

  async function submit() {
    if (!attemptId || !quiz) return;

    const payload = {
      answers: quiz.questions.map((q) => ({
        question_id: q.id,
        selected_option_ids: answers[q.id] ?? [],
        free_text_answer: freeTextAnswers[q.id] ?? null,
      })),
    };

    const res = await fetch(`/api/v1/quiz-attempts/${attemptId}/submit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const body = await res.json();

    if (res.ok) {
      setResult(body.data);
      router.refresh();
    }
  }

  if (loading) return <p className="text-sm text-neutral-500">Loading quiz…</p>;
  if (!quiz) return <p className="text-sm text-neutral-500">Quiz not found.</p>;

  if (result) {
    return (
      <div className="rounded-xl border border-neutral-200 bg-white p-6 text-center">
        <p className={`text-3xl font-bold ${result.passed ? 'text-green-600' : 'text-red-600'}`}>
          {result.score_percent}%
        </p>
        <p className="mt-1 text-sm text-neutral-600">
          {result.passed ? 'You passed!' : `You need ${quiz.passing_score_percent}% to pass.`}
        </p>
        {!result.passed && quiz.can_attempt && (
          <button
            onClick={() => {
              setResult(null);
              setAttemptId(null);
            }}
            className="mt-4 rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90"
          >
            Try Again
          </button>
        )}
      </div>
    );
  }

  if (!attemptId) {
    return (
      <div className="rounded-xl border border-neutral-200 bg-white p-6">
        <p className="text-sm text-neutral-600">
          {quiz.questions.length} questions &middot; Passing score: {quiz.passing_score_percent}%
          {quiz.max_attempts && ` · ${quiz.attempts_used}/${quiz.max_attempts} attempts used`}
        </p>
        {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
        <button
          onClick={startAttempt}
          disabled={!quiz.can_attempt}
          className="mt-4 rounded-md bg-[var(--brand-accent)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {quiz.can_attempt ? 'Start Quiz' : 'No attempts remaining'}
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {quiz.questions.map((question, index) => (
        <div key={question.id} className="rounded-xl border border-neutral-200 bg-white p-5">
          <p className="font-medium text-neutral-900">
            {index + 1}. {question.question_text}
          </p>
          <div className="mt-3 space-y-2">
            {question.type === 'free_text' ? (
              <textarea
                rows={3}
                value={freeTextAnswers[question.id] ?? ''}
                onChange={(e) => setFreeTextAnswers((prev) => ({ ...prev, [question.id]: e.target.value }))}
                className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
                placeholder="Your answer (reviewed manually)"
              />
            ) : (
              question.options.map((option) => (
                <label key={option.id} className="flex items-center gap-2 text-sm text-neutral-700">
                  <input
                    type={question.type === 'multiple_answer' ? 'checkbox' : 'radio'}
                    name={`question-${question.id}`}
                    checked={(answers[question.id] ?? []).includes(option.id)}
                    onChange={() => toggleOption(question.id, option.id, question.type !== 'multiple_answer')}
                  />
                  {option.option_text}
                </label>
              ))
            )}
          </div>
        </div>
      ))}
      <button
        onClick={submit}
        className="w-full rounded-md bg-[var(--brand-accent)] px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90"
      >
        Submit Quiz
      </button>
    </div>
  );
}
