'use client';

import { useState } from 'react';

interface ExportJob {
  id: number;
  status: string;
  file_size_bytes: number | null;
  created_at: string;
}

export function TenantExportsManager({ initial }: { initial: ExportJob[] }) {
  const [exports, setExports] = useState(initial);
  const [pending, setPending] = useState(false);

  async function refresh() {
    const res = await fetch('/api/v1/admin/exports');
    const body = await res.json();
    setExports(body.data ?? []);
  }

  async function requestExport() {
    setPending(true);
    try {
      await fetch('/api/v1/admin/exports', { method: 'POST' });
      await refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="rounded-xl border border-neutral-200 bg-white p-5">
      <div className="flex items-center justify-between">
        <p className="text-sm text-neutral-600">
          Download a complete copy of your academy&apos;s data — courses, learners, enrolments, and more.
        </p>
        <button
          onClick={requestExport}
          disabled={pending}
          className="shrink-0 rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Requesting…' : 'Request export'}
        </button>
      </div>

      <ul className="mt-4 divide-y divide-neutral-100">
        {exports.map((exportJob) => (
          <li key={exportJob.id} className="flex items-center justify-between py-3 text-sm">
            <span className="text-neutral-600">
              {new Date(exportJob.created_at).toLocaleString()}
              {exportJob.file_size_bytes && ` · ${Math.round(exportJob.file_size_bytes / 1024)} KB`}
            </span>
            <div className="flex items-center gap-3">
              <span
                className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                  exportJob.status === 'completed'
                    ? 'bg-green-100 text-green-700'
                    : exportJob.status === 'failed'
                      ? 'bg-red-100 text-red-700'
                      : 'bg-neutral-100 text-neutral-600'
                }`}
              >
                {exportJob.status}
              </span>
              {exportJob.status === 'completed' && (
                <a
                  href={`/api/v1/admin/exports/${exportJob.id}/download`}
                  className="text-xs font-semibold text-[var(--tenant-primary)] hover:underline"
                >
                  Download
                </a>
              )}
            </div>
          </li>
        ))}
        {exports.length === 0 && <p className="py-3 text-sm text-neutral-500">No exports requested yet.</p>}
      </ul>
    </div>
  );
}
