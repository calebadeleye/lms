'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

interface ExportJob {
  id: number;
  status: string;
  file_size_bytes: number | null;
  created_at: string;
  completed_at: string | null;
}

export function PlatformTenantOffboarding({
  tenantId,
  status,
  deletionScheduledAt,
  initialExports,
}: {
  tenantId: string;
  status: string;
  deletionScheduledAt: string | null;
  initialExports: ExportJob[];
}) {
  const router = useRouter();
  const [exports, setExports] = useState(initialExports);
  const [pending, setPending] = useState<string | null>(null);
  const [retentionDays, setRetentionDays] = useState('30');

  async function refreshExports() {
    const res = await fetch(`/api/v1/platform/tenants/${tenantId}/exports`);
    const body = await res.json();
    setExports(body.data ?? []);
  }

  async function requestExport() {
    setPending('export');
    try {
      await fetch(`/api/v1/platform/tenants/${tenantId}/exports`, { method: 'POST' });
      await refreshExports();
    } finally {
      setPending(null);
    }
  }

  async function scheduleDeletion() {
    if (!window.confirm(`Schedule permanent deletion in ${retentionDays} day(s)? This can be cancelled before it runs.`)) {
      return;
    }
    setPending('schedule');
    try {
      await fetch(`/api/v1/platform/tenants/${tenantId}/schedule-deletion`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ retention_days: Number(retentionDays) }),
      });
      router.refresh();
    } finally {
      setPending(null);
    }
  }

  async function cancelDeletion() {
    setPending('cancel');
    try {
      await fetch(`/api/v1/platform/tenants/${tenantId}/cancel-deletion`, { method: 'POST' });
      router.refresh();
    } finally {
      setPending(null);
    }
  }

  const hasCompletedExport = exports.some((e) => e.status === 'completed');

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-white">Data exports</h2>
          <button
            onClick={requestExport}
            disabled={pending === 'export'}
            className="rounded-full bg-[var(--naitalk-green)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-60"
          >
            {pending === 'export' ? 'Requesting…' : 'Request export'}
          </button>
        </div>

        <ul className="mt-4 divide-y divide-white/10">
          {exports.map((exportJob) => (
            <li key={exportJob.id} className="flex items-center justify-between py-2 text-sm">
              <span className="text-white/60">
                {new Date(exportJob.created_at).toLocaleString()}
                {exportJob.file_size_bytes && ` · ${Math.round(exportJob.file_size_bytes / 1024)} KB`}
              </span>
              <div className="flex items-center gap-3">
                <span
                  className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                    exportJob.status === 'completed'
                      ? 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]'
                      : exportJob.status === 'failed'
                        ? 'bg-red-500/15 text-red-400'
                        : 'bg-white/10 text-white/60'
                  }`}
                >
                  {exportJob.status}
                </span>
                {exportJob.status === 'completed' && (
                  <a
                    href={`/api/v1/platform/tenants/${tenantId}/exports/${exportJob.id}/download`}
                    className="text-xs font-medium text-white/70 hover:underline"
                  >
                    Download
                  </a>
                )}
              </div>
            </li>
          ))}
          {exports.length === 0 && <p className="py-2 text-sm text-white/40">No exports requested yet.</p>}
        </ul>
      </div>

      <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
        <h2 className="text-sm font-semibold text-white">Offboarding</h2>

        {status === 'deletion_scheduled' ? (
          <div className="mt-3">
            <p className="text-sm text-white/60">
              Permanent deletion scheduled for{' '}
              <span className="font-medium text-white">
                {deletionScheduledAt ? new Date(deletionScheduledAt).toLocaleString() : '—'}
              </span>
              .
            </p>
            <button
              onClick={cancelDeletion}
              disabled={pending === 'cancel'}
              className="mt-3 rounded-full border border-white/15 px-4 py-2 text-sm font-semibold text-white/80 hover:bg-white/5 disabled:opacity-60"
            >
              {pending === 'cancel' ? 'Cancelling…' : 'Cancel scheduled deletion'}
            </button>
          </div>
        ) : (
          <div className="mt-3">
            <p className="text-sm text-white/60">
              Schedules the tenant for permanent deletion after a grace period. The scheduled job that
              actually deletes refuses to run unless a completed export already exists —{' '}
              {hasCompletedExport ? 'one does.' : 'request one above first.'}
            </p>
            <div className="mt-3 flex items-end gap-3">
              <div>
                <label className="block text-xs font-medium text-white/60">Grace period (days)</label>
                <input
                  type="number"
                  min={1}
                  max={365}
                  value={retentionDays}
                  onChange={(e) => setRetentionDays(e.target.value)}
                  className="mt-1 w-24 rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-[var(--naitalk-green)] focus:outline-none"
                />
              </div>
              <button
                onClick={scheduleDeletion}
                disabled={pending === 'schedule'}
                className="rounded-full border border-red-500/30 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500/10 disabled:opacity-60"
              >
                {pending === 'schedule' ? 'Scheduling…' : 'Schedule deletion'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
