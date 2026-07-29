'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { AdminStudent } from '@/lib/admin-users-types';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';

const PER_PAGE = 20;

export function AdminStudentsManager({ initial, initialMeta }: { initial: AdminStudent[]; initialMeta: PaginationMeta }) {
  const router = useRouter();
  const [students, setStudents] = useState(initial);
  const [meta, setMeta] = useState(initialMeta);
  const [showInactive, setShowInactive] = useState(false);
  const [search, setSearch] = useState('');

  async function loadStudents({
    page = 1,
    includeInactive = showInactive,
    query = search,
  }: { page?: number; includeInactive?: boolean; query?: string } = {}) {
    const params = new URLSearchParams({ page: String(page), per_page: String(PER_PAGE) });
    if (includeInactive) params.set('include_inactive', '1');
    if (query) params.set('search', query);

    const res = await fetch(`/api/v1/admin/students?${params.toString()}`);
    const body = await res.json();
    setStudents(body.data ?? []);
    setMeta(body.meta?.pagination ?? { page: 1, per_page: PER_PAGE, total: (body.data ?? []).length });
  }

  async function toggleShowInactive() {
    const next = !showInactive;
    setShowInactive(next);
    await loadStudents({ includeInactive: next });
  }

  async function handleSearch(query: string) {
    setSearch(query);
    await loadStudents({ query, page: 1 });
  }

  async function deactivate(studentId: number) {
    if (!window.confirm('Remove this student from the roster? Their enrolments and progress are kept.')) return;
    await fetch(`/api/v1/admin/students/${studentId}`, { method: 'DELETE' });
    await loadStudents();
    router.refresh();
  }

  async function reactivate(studentId: number) {
    await fetch(`/api/v1/admin/students/${studentId}/reactivate`, { method: 'POST' });
    await loadStudents();
    router.refresh();
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3">
        <h2 className="text-sm font-semibold text-neutral-900">{meta.total} student(s)</h2>
        <div className="flex items-center gap-3">
          <SearchInput placeholder="Search by name or email…" onSearch={handleSearch} />
          <label className="flex items-center gap-1.5 whitespace-nowrap text-xs text-neutral-600">
            <input type="checkbox" checked={showInactive} onChange={toggleShowInactive} />
            Show removed
          </label>
        </div>
      </div>
      <table className="w-full text-left text-sm">
        <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500">
          <tr>
            <th className="px-4 py-3">Name</th>
            <th className="px-4 py-3">Email</th>
            <th className="px-4 py-3">Enrolments</th>
            <th className="px-4 py-3">Completed</th>
            <th className="px-4 py-3">Certificates</th>
            <th className="px-4 py-3">Joined</th>
            <th className="px-4 py-3" />
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">
          {students.map((student) => (
            <tr key={student.id}>
              <td className="px-4 py-3 font-medium text-neutral-900">{student.name}</td>
              <td className="px-4 py-3 text-neutral-600">{student.email}</td>
              <td className="px-4 py-3 text-neutral-600">{student.enrolments_count}</td>
              <td className="px-4 py-3 text-neutral-600">{student.completed_count}</td>
              <td className="px-4 py-3 text-neutral-600">{student.certificates_count}</td>
              <td className="px-4 py-3 text-neutral-600">{new Date(student.joined_at).toLocaleDateString()}</td>
              <td className="px-4 py-3 text-right text-xs font-medium">
                {student.status === 'active' ? (
                  <button onClick={() => deactivate(student.id)} className="text-red-600 hover:underline">
                    Remove
                  </button>
                ) : (
                  <button onClick={() => reactivate(student.id)} className="text-neutral-700 hover:underline">
                    Restore
                  </button>
                )}
              </td>
            </tr>
          ))}
          {students.length === 0 && (
            <tr>
              <td colSpan={7} className="px-4 py-6 text-center text-neutral-500">
                No students found.
              </td>
            </tr>
          )}
        </tbody>
      </table>
      <Pagination meta={meta} onPageChange={(page) => loadStudents({ page })} />
    </div>
  );
}
