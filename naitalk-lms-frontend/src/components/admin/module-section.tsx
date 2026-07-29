'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminModule } from '@/lib/admin-course-types';
import { LessonRow } from '@/components/admin/lesson-row';

export function ModuleSection({ module }: { module: AdminModule }) {
  const router = useRouter();
  const [newLessonTitle, setNewLessonTitle] = useState('');
  const [adding, setAdding] = useState(false);

  async function addLesson(e: React.FormEvent) {
    e.preventDefault();
    setAdding(true);
    try {
      await fetch(`/api/v1/admin/modules/${module.id}/lessons`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: newLessonTitle, type: 'rich_text' }),
      });
      setNewLessonTitle('');
      router.refresh();
    } finally {
      setAdding(false);
    }
  }

  async function deleteModule() {
    if (!window.confirm(`Delete module "${module.title}" and all its lessons?`)) return;
    await fetch(`/api/v1/admin/modules/${module.id}`, { method: 'DELETE' });
    router.refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white">
      <div className="flex items-center justify-between px-4 py-3">
        <h3 className="text-sm font-semibold text-neutral-900">{module.title}</h3>
        <button onClick={deleteModule} className="text-xs font-medium text-red-600">
          Delete module
        </button>
      </div>

      <div>
        {module.lessons.map((lesson) => (
          <LessonRow key={lesson.id} lesson={lesson} />
        ))}
      </div>

      <form onSubmit={addLesson} className="flex gap-2 border-t border-neutral-100 px-4 py-3">
        <input
          value={newLessonTitle}
          onChange={(e) => setNewLessonTitle(e.target.value)}
          placeholder="New lesson title"
          className="flex-1 rounded-md border border-neutral-300 px-2 py-1.5 text-sm"
        />
        <button
          type="submit"
          disabled={adding || !newLessonTitle}
          className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium hover:bg-neutral-50 disabled:opacity-60"
        >
          + Add lesson
        </button>
      </form>
    </div>
  );
}
