'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { formatPrice } from '@/lib/learning-types';
import type { CourseCategory } from '@/lib/admin-course-types';
import { Pagination, type PaginationMeta } from '@/components/pagination';

const PER_PAGE = 20;

export interface AdminCourse {
  id: number;
  title: string;
  status: string;
  pricing_type: string;
  price_cents: number;
  currency: string;
  thumbnail_url: string | null;
  category: { id: number; name: string } | null;
  enrolments_count: number;
}

export function AdminCoursesManager({
  initial,
  initialMeta,
  categories,
}: {
  initial: AdminCourse[];
  initialMeta: PaginationMeta;
  categories: CourseCategory[];
}) {
  const router = useRouter();
  const [courses, setCourses] = useState(initial);
  const [meta, setMeta] = useState(initialMeta);
  const [title, setTitle] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [pricingType, setPricingType] = useState<'free' | 'paid'>('free');
  const [priceNaira, setPriceNaira] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [bulkPending, setBulkPending] = useState(false);
  const [rowPending, setRowPending] = useState<Set<number>>(new Set());

  async function loadCourses(page: number) {
    const res = await fetch(`/api/v1/admin/courses?page=${page}&per_page=${PER_PAGE}`);
    const body = await res.json();
    setCourses(body.data ?? []);
    setMeta(body.meta?.pagination ?? { page: 1, per_page: PER_PAGE, total: (body.data ?? []).length });
    setSelectedIds(new Set());
  }

  function toggleSelected(courseId: number) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(courseId)) next.delete(courseId);
      else next.add(courseId);
      return next;
    });
  }

  function toggleSelectAll() {
    setSelectedIds((prev) => (prev.size === courses.length ? new Set() : new Set(courses.map((c) => c.id))));
  }

  async function bulkUnpublish() {
    if (!window.confirm(`Unpublish ${selectedIds.size} course(s)?`)) return;
    setBulkPending(true);
    try {
      await Promise.all(
        [...selectedIds].map((id) => fetch(`/api/v1/admin/courses/${id}/unpublish`, { method: 'POST' }))
      );
      setCourses((prev) => prev.map((c) => (selectedIds.has(c.id) ? { ...c, status: 'draft' } : c)));
      setSelectedIds(new Set());
      router.refresh();
    } finally {
      setBulkPending(false);
    }
  }

  async function bulkDelete() {
    if (!window.confirm(`Delete ${selectedIds.size} course(s)? This cannot be undone.`)) return;
    setBulkPending(true);
    try {
      await Promise.all([...selectedIds].map((id) => fetch(`/api/v1/admin/courses/${id}`, { method: 'DELETE' })));
      await loadCourses(meta.page);
      router.refresh();
    } finally {
      setBulkPending(false);
    }
  }

  async function createCourse(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const res = await fetch('/api/v1/admin/courses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title,
          category_id: categoryId ? Number(categoryId) : undefined,
          pricing_type: pricingType,
          price_cents: pricingType === 'paid' ? Math.round(Number(priceNaira) * 100) : 0,
        }),
      });
      const body = await res.json();

      if (!res.ok) {
        setError(body?.errors?.title?.[0] ?? 'Could not create course.');
        return;
      }

      // Straight into the builder rather than leaving the admin on this
      // list wondering what to do next — the single biggest source of
      // "how do I actually add content to my course" confusion.
      router.push(`/admin/courses/${body.data.id}`);
    } finally {
      setPending(false);
    }
  }

  async function togglePublish(course: AdminCourse) {
    const action = course.status === 'published' ? 'unpublish' : 'publish';
    setRowPending((prev) => new Set(prev).add(course.id));
    try {
      const res = await fetch(`/api/v1/admin/courses/${course.id}/${action}`, { method: 'POST' });
      const body = await res.json();
      if (res.ok) {
        setError(null);
        setCourses((prev) => prev.map((c) => (c.id === course.id ? { ...c, status: body.data.status } : c)));
        router.refresh();
      } else {
        setError(body?.errors?.course?.[0] ?? `Could not ${action} this course.`);
      }
    } finally {
      setRowPending((prev) => {
        const next = new Set(prev);
        next.delete(course.id);
        return next;
      });
    }
  }

  async function remove(courseId: number) {
    if (!window.confirm('Delete this course? This cannot be undone.')) return;
    setRowPending((prev) => new Set(prev).add(courseId));
    try {
      await fetch(`/api/v1/admin/courses/${courseId}`, { method: 'DELETE' });
      await loadCourses(meta.page);
    } finally {
      setRowPending((prev) => {
        const next = new Set(prev);
        next.delete(courseId);
        return next;
      });
    }
  }

  return (
    <div className="space-y-6">
      <form onSubmit={createCourse} className="grid gap-3 rounded-xl border border-neutral-200 bg-white p-4 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <label className="block text-xs font-medium text-neutral-700">Course title</label>
          <input
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Onboarding Essentials"
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
          <label className="block text-xs font-medium text-neutral-700">Pricing</label>
          <select
            value={pricingType}
            onChange={(e) => setPricingType(e.target.value as 'free' | 'paid')}
            className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
          >
            <option value="free">Free</option>
            <option value="paid">Paid</option>
          </select>
        </div>
        {pricingType === 'paid' && (
          <div>
            <label className="block text-xs font-medium text-neutral-700">Price (₦)</label>
            <input
              type="number"
              min="0"
              value={priceNaira}
              onChange={(e) => setPriceNaira(e.target.value)}
              className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"
            />
          </div>
        )}
        <div className="sm:col-span-4">
          {error && <p className="mb-2 text-sm text-red-600">{error}</p>}
          <button
            type="submit"
            disabled={pending || !title}
            className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 hover:opacity-90 disabled:opacity-60"
          >
            {pending ? 'Creating…' : 'Create course & start editing'}
          </button>
          <p className="mt-1.5 text-xs text-neutral-400">
            You&apos;ll go straight into the course builder to add a description, modules, and lessons.
          </p>
        </div>
      </form>

      {selectedIds.size > 0 && (
        <div className="flex items-center justify-between rounded-xl border border-[var(--brand-primary)]/30 bg-[var(--brand-primary)]/5 px-4 py-2.5">
          <span className="text-sm font-medium text-neutral-700">{selectedIds.size} selected</span>
          <div className="flex items-center gap-3">
            <button
              onClick={bulkUnpublish}
              disabled={bulkPending}
              className="text-sm font-medium text-neutral-700 hover:underline disabled:opacity-60"
            >
              Unpublish selected
            </button>
            <button
              onClick={bulkDelete}
              disabled={bulkPending}
              className="text-sm font-medium text-red-600 hover:underline disabled:opacity-60"
            >
              Delete selected
            </button>
            <button onClick={() => setSelectedIds(new Set())} className="text-sm text-neutral-400 hover:text-neutral-600">
              Clear
            </button>
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500">
            <tr>
              <th className="w-10 px-4 py-3">
                <input
                  type="checkbox"
                  checked={courses.length > 0 && selectedIds.size === courses.length}
                  onChange={toggleSelectAll}
                  aria-label="Select all courses"
                />
              </th>
              <th className="px-4 py-3" />
              <th className="px-4 py-3">Title</th>
              <th className="px-4 py-3">Category</th>
              <th className="px-4 py-3">Price</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Enrolments</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {courses.map((course) => (
              <tr key={course.id} className={selectedIds.has(course.id) ? 'bg-[var(--brand-primary)]/5' : undefined}>
                <td className="px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedIds.has(course.id)}
                    onChange={() => toggleSelected(course.id)}
                    aria-label={`Select ${course.title}`}
                  />
                </td>
                <td className="px-2 py-3">
                  {course.thumbnail_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={course.thumbnail_url} alt="" className="h-8 w-14 rounded object-cover" />
                  ) : (
                    <div className="h-8 w-14 rounded bg-linear-to-br from-[var(--brand-primary)] to-[var(--brand-secondary)]/60" />
                  )}
                </td>
                <td className="px-4 py-3 font-medium text-neutral-900">
                  <Link href={`/admin/courses/${course.id}`} className="hover:underline">
                    {course.title}
                  </Link>
                </td>
                <td className="px-4 py-3 text-neutral-600">{course.category?.name ?? '—'}</td>
                <td className="px-4 py-3 text-neutral-600">{formatPrice(course.price_cents, course.currency)}</td>
                <td className="px-4 py-3">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                      course.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-700'
                    }`}
                  >
                    {course.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-neutral-600">{course.enrolments_count}</td>
                <td className="px-4 py-3 text-right text-xs font-medium whitespace-nowrap">
                  <Link href={`/admin/courses/${course.id}`} className="mr-3 font-semibold text-[var(--brand-primary)] hover:underline">
                    Edit
                  </Link>
                  <button
                    onClick={() => togglePublish(course)}
                    disabled={rowPending.has(course.id)}
                    className="mr-3 text-neutral-700 hover:underline disabled:opacity-50"
                  >
                    {rowPending.has(course.id)
                      ? course.status === 'published'
                        ? 'Unpublishing…'
                        : 'Publishing…'
                      : course.status === 'published'
                        ? 'Unpublish'
                        : 'Publish'}
                  </button>
                  <button
                    onClick={() => remove(course.id)}
                    disabled={rowPending.has(course.id)}
                    className="text-red-600 hover:underline disabled:opacity-50"
                  >
                    {rowPending.has(course.id) ? 'Deleting…' : 'Delete'}
                  </button>
                </td>
              </tr>
            ))}
            {courses.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-6 text-center text-neutral-500">
                  No courses yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
        <Pagination meta={meta} onPageChange={loadCourses} />
      </div>
    </div>
  );
}
