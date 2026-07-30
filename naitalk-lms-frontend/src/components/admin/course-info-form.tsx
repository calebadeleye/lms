'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminCourseDetail, CourseCategory } from '@/lib/admin-course-types';

export function CourseInfoForm({ course, categories }: { course: AdminCourseDetail; categories: CourseCategory[] }) {
  const router = useRouter();
  const [title, setTitle] = useState(course.title);
  const [excerpt, setExcerpt] = useState(course.excerpt ?? '');
  const [description, setDescription] = useState(course.description ?? '');
  const [categoryId, setCategoryId] = useState(course.category?.id.toString() ?? '');
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    try {
      await fetch(`/api/v1/admin/courses/${course.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title,
          excerpt,
          description,
          category_id: categoryId ? Number(categoryId) : null,
          pricing_type: course.pricing_type,
        }),
      });
      setSaved(true);
      router.refresh();
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={save} className="space-y-4 rounded-xl border border-neutral-200 bg-white p-5">
      <div>
        <label className="block text-xs font-medium text-neutral-700">Title</label>
        <input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-neutral-700">Excerpt</label>
        <input
          value={excerpt}
          onChange={(e) => setExcerpt(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
      </div>
      <div>
        <label className="block text-xs font-medium text-neutral-700">Category</label>
        <select
          value={categoryId}
          onChange={(e) => setCategoryId(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        >
          <option value="">Uncategorized</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="block text-xs font-medium text-neutral-700">Description</label>
        <textarea
          rows={4}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
      </div>
      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={saving}
          className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {saving ? 'Saving…' : 'Save course details'}
        </button>
        {saved && <span className="text-sm text-green-600">Saved.</span>}
      </div>
    </form>
  );
}
