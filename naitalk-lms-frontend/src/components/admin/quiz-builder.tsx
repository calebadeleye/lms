'use client';

import { useState } from 'react';
import type { AdminQuizQuestion } from '@/lib/admin-course-types';

export function QuizBuilder({
  lessonId,
  initial,
  onSaved,
}: {
  lessonId: number;
  initial: AdminQuizQuestion[];
  onSaved: () => void;
}) {
  const [questions, setQuestions] = useState<AdminQuizQuestion[]>(
    initial.length > 0 ? initial : [{ type: 'multiple_choice', question_text: '', points: 1, options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }] }],
  );
  const [passingScore, setPassingScore] = useState(70);
  const [saving, setSaving] = useState(false);

  function addQuestion() {
    setQuestions((prev) => [
      ...prev,
      { type: 'multiple_choice', question_text: '', points: 1, options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }] },
    ]);
  }

  function updateQuestion(index: number, patch: Partial<AdminQuizQuestion>) {
    setQuestions((prev) => prev.map((q, i) => (i === index ? { ...q, ...patch } : q)));
  }

  function updateOption(qIndex: number, oIndex: number, patch: Partial<{ option_text: string; is_correct: boolean }>) {
    setQuestions((prev) =>
      prev.map((q, i) =>
        i === qIndex
          ? {
              ...q,
              options: q.options.map((o, j) => {
                if (j !== oIndex) return o;
                return { ...o, ...patch };
              }),
            }
          : q,
      ),
    );
  }

  function setSingleCorrect(qIndex: number, oIndex: number) {
    setQuestions((prev) =>
      prev.map((q, i) =>
        i === qIndex ? { ...q, options: q.options.map((o, j) => ({ ...o, is_correct: j === oIndex })) } : q,
      ),
    );
  }

  function addOption(qIndex: number) {
    setQuestions((prev) =>
      prev.map((q, i) => (i === qIndex ? { ...q, options: [...q.options, { option_text: '', is_correct: false }] } : q)),
    );
  }

  function removeQuestion(index: number) {
    setQuestions((prev) => prev.filter((_, i) => i !== index));
  }

  async function save() {
    setSaving(true);
    try {
      await fetch(`/api/v1/admin/lessons/${lessonId}/quiz`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ passing_score_percent: passingScore, questions }),
      });
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-4 rounded-md bg-neutral-50 p-4">
      <div className="flex items-center gap-2 text-sm">
        <label className="font-medium text-neutral-700">Passing score %</label>
        <input
          type="number"
          min={0}
          max={100}
          value={passingScore}
          onChange={(e) => setPassingScore(Number(e.target.value))}
          className="w-20 rounded-md border border-neutral-300 px-2 py-1"
        />
      </div>

      {questions.map((question, qIndex) => (
        <div key={qIndex} className="rounded-md border border-neutral-200 bg-white p-3">
          <div className="flex gap-2">
            <input
              value={question.question_text}
              onChange={(e) => updateQuestion(qIndex, { question_text: e.target.value })}
              placeholder="Question text"
              className="flex-1 rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
            />
            <select
              value={question.type}
              onChange={(e) => updateQuestion(qIndex, { type: e.target.value as AdminQuizQuestion['type'] })}
              className="rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm"
            >
              <option value="multiple_choice">Single choice</option>
              <option value="multiple_answer">Multiple answer</option>
              <option value="true_false">True/False</option>
              <option value="free_text">Free text</option>
            </select>
            <button onClick={() => removeQuestion(qIndex)} className="text-xs text-red-600">
              Remove
            </button>
          </div>

          {question.type !== 'free_text' && (
            <div className="mt-2 space-y-1.5 pl-2">
              {question.options.map((option, oIndex) => (
                <div key={oIndex} className="flex items-center gap-2">
                  <input
                    type={question.type === 'multiple_answer' ? 'checkbox' : 'radio'}
                    name={`correct-${qIndex}`}
                    checked={option.is_correct}
                    onChange={() =>
                      question.type === 'multiple_answer'
                        ? updateOption(qIndex, oIndex, { is_correct: !option.is_correct })
                        : setSingleCorrect(qIndex, oIndex)
                    }
                  />
                  <input
                    value={option.option_text}
                    onChange={(e) => updateOption(qIndex, oIndex, { option_text: e.target.value })}
                    placeholder="Option text"
                    className="flex-1 rounded-md border border-neutral-300 px-2 py-1 text-sm"
                  />
                </div>
              ))}
              {question.type !== 'true_false' && (
                <button onClick={() => addOption(qIndex)} className="text-xs font-medium text-[var(--brand-primary)]">
                  + Add option
                </button>
              )}
            </div>
          )}
        </div>
      ))}

      <div className="flex gap-3">
        <button onClick={addQuestion} className="text-xs font-medium text-[var(--brand-primary)]">
          + Add question
        </button>
        <button
          onClick={save}
          disabled={saving}
          className="rounded-md bg-[var(--brand-accent)] px-4 py-1.5 text-xs font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
        >
          {saving ? 'Saving…' : 'Save quiz'}
        </button>
      </div>
    </div>
  );
}
