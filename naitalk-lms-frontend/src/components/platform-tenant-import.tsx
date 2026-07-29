'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function PlatformTenantImport() {
  const router = useRouter();
  const [newTenantName, setNewTenantName] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file || !newTenantName.trim()) return;
    setPending(true);
    setError(null);
    setSuccess(null);

    try {
      let payload: unknown;
      try {
        payload = JSON.parse(await file.text());
      } catch {
        setError('That file is not valid JSON — use a file downloaded from a tenant export.');
        return;
      }

      const res = await fetch('/api/v1/platform/tenants/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ new_tenant_name: newTenantName, payload }),
      });
      const body = await res.json().catch(() => null);

      if (!res.ok) {
        setError(body?.errors?.payload?.[0] ?? body?.errors?.[0]?.message ?? 'Import failed.');
        return;
      }

      setSuccess(`Restored as "${body.data.name}".`);
      setNewTenantName('');
      setFile(null);
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
      <h2 className="text-sm font-semibold text-white">Restore a tenant from an export</h2>
      <p className="mt-1 text-xs text-white/50">
        Upload a previously downloaded export file. This always creates a brand-new tenant — it never overwrites or
        merges into an existing one.
      </p>

      <form onSubmit={submit} className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
        <input
          required
          value={newTenantName}
          onChange={(e) => setNewTenantName(e.target.value)}
          placeholder="New tenant name"
          className="rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--naitalk-green)] focus:outline-none"
        />
        <input
          type="file"
          accept="application/json"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="text-sm text-white/70 file:mr-3 file:rounded-full file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white"
        />
        <button
          type="submit"
          disabled={pending || !file || !newTenantName.trim()}
          className="rounded-full bg-[var(--naitalk-green)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          {pending ? 'Restoring…' : 'Restore'}
        </button>
      </form>
      {error && <p className="mt-2 text-xs text-red-400">{error}</p>}
      {success && <p className="mt-2 text-xs text-[var(--naitalk-green)]">{success}</p>}
    </div>
  );
}
