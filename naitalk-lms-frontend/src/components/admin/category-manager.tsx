'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { CourseCategory } from '@/lib/admin-course-types';

export function CategoryManager({ initial }: { initial: CourseCategory[] }) {
  const router = useRouter();
  const [categories, setCategories] = useState(initial);
  const [newName, setNewName] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function addCategory(e: React.FormEvent) {
    e.preventDefault();
    if (!newName.trim()) return;
    setPending(true);
    setError(null);

    try {
      const res = await fetch('/api/v1/admin/course-categories', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newName }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.name?.[0] ?? body?.errors?.[0]?.message ?? 'Could not add category.');
        return;
      }

      setCategories((prev) => [...prev, body.data].sort((a, b) => a.name.localeCompare(b.name)));
      setNewName('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function rename(category: CourseCategory, name: string) {
    if (!name.trim() || name === category.name) return;
    await fetch(`/api/v1/admin/course-categories/${category.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name }),
    });
    setCategories((prev) => prev.map((c) => (c.id === category.id ? { ...c, name } : c)));
    router.refresh();
  }

  async function remove(category: CourseCategory) {
    if (!window.confirm(`Delete category "${category.name}"? Courses in it will become uncategorized.`)) return;
    await fetch(`/api/v1/admin/course-categories/${category.id}`, { method: 'DELETE' });
    setCategories((prev) => prev.filter((c) => c.id !== category.id));
    router.refresh();
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-4">
      <h2 className="text-sm font-semibold text-neutral-900">Categories</h2>
      <ul className="mt-3 space-y-2">
        {categories.map((category) => (
          <li key={category.id} className="flex items-center gap-2">
            <input
              defaultValue={category.name}
              onBlur={(e) => rename(category, e.target.value)}
              className="w-full rounded-md border border-neutral-300 px-2 py-1 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
            />
            <button onClick={() => remove(category)} className="text-xs font-medium text-red-600 hover:underline">
              Delete
            </button>
          </li>
        ))}
        {categories.length === 0 && <li className="text-sm text-neutral-500">No categories yet.</li>}
      </ul>

      <form onSubmit={addCategory} className="mt-3 flex gap-2">
        <input
          value={newName}
          onChange={(e) => setNewName(e.target.value)}
          placeholder="New category name"
          className="w-full rounded-md border border-neutral-300 px-2 py-1.5 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
        />
        <button
          type="submit"
          disabled={pending || !newName.trim()}
          className="whitespace-nowrap rounded-md bg-[var(--brand-accent)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Adding…' : 'Add'}
        </button>
      </form>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
    </div>
  );
}
