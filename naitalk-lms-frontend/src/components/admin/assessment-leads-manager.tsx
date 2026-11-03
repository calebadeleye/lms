'use client';

import { useState } from 'react';
import { Pagination, type PaginationMeta } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';

const PER_PAGE = 20;

export interface AssessmentLead {
  id: number;
  full_name: string;
  email: string;
  source: string | null;
  request_count: number;
  created_at: string;
  last_requested_at: string | null;
  email_sent: boolean;
}

const SOURCE_LABELS: Record<string, string> = { landing: 'Free assessment page', home: 'Homepage' };

function formatDate(value: string | null) {
  return value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

export function AssessmentLeadsManager({ initial, initialMeta }: { initial: AssessmentLead[]; initialMeta: PaginationMeta }) {
  const [leads, setLeads] = useState(initial);
  const [meta, setMeta] = useState(initialMeta);
  const [search, setSearch] = useState('');

  async function load({ page = 1, query = search }: { page?: number; query?: string } = {}) {
    const params = new URLSearchParams({ page: String(page), per_page: String(PER_PAGE) });
    if (query) params.set('search', query);
    const res = await fetch(`/api/v1/admin/assessment-leads?${params.toString()}`);
    const body = await res.json();
    setLeads(body.data ?? []);
    setMeta(body.meta?.pagination ?? { page: 1, per_page: PER_PAGE, total: (body.data ?? []).length });
  }

  async function handleSearch(query: string) {
    setSearch(query);
    await load({ query, page: 1 });
  }

  const exportHref = `/api/v1/admin/assessment-leads/export${search ? `?search=${encodeURIComponent(search)}` : ''}`;

  return (
    <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3">
        <h2 className="text-sm font-semibold text-neutral-900">{meta.total} lead(s)</h2>
        <div className="flex flex-wrap items-center gap-3">
          <SearchInput placeholder="Search name or email" onSearch={handleSearch} />
          <a
            href={exportHref}
            download={`free-assessment-leads.csv`}
            className="rounded-md border border-neutral-300 px-3 py-1.5 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
          >
            Download CSV
          </a>
        </div>
      </div>
      <table className="w-full text-left text-sm">
        <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase text-neutral-500">
          <tr>
            <th className="px-4 py-3">Name</th>
            <th className="px-4 py-3">Email</th>
            <th className="px-4 py-3">Signed up from</th>
            <th className="px-4 py-3">Requests</th>
            <th className="px-4 py-3">First signed up</th>
            <th className="px-4 py-3">Email status</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-neutral-100">
          {leads.map((lead) => (
            <tr key={lead.id}>
              <td className="px-4 py-3 font-medium text-neutral-900">{lead.full_name}</td>
              <td className="px-4 py-3 text-neutral-600">
                <a href={`mailto:${lead.email}`} className="hover:underline">{lead.email}</a>
              </td>
              <td className="px-4 py-3 text-neutral-600">{lead.source ? (SOURCE_LABELS[lead.source] ?? lead.source) : '—'}</td>
              <td className="px-4 py-3 text-neutral-600">{lead.request_count}</td>
              <td className="px-4 py-3 text-neutral-600">{formatDate(lead.created_at)}</td>
              <td className="px-4 py-3">
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${lead.email_sent ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800'}`}>
                  {lead.email_sent ? 'Sent' : 'Not sent'}
                </span>
              </td>
            </tr>
          ))}
          {leads.length === 0 && (
            <tr>
              <td colSpan={6} className="px-4 py-6 text-center text-neutral-500">
                {search ? 'No leads match your search.' : 'No one has requested the free assessment yet.'}
              </td>
            </tr>
          )}
        </tbody>
      </table>
      <Pagination meta={meta} onPageChange={(page) => load({ page })} />
    </div>
  );
}
