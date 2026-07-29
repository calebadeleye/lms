'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

interface Domain {
  id: number;
  hostname: string;
  domain_type: string;
  verification_status: string;
  verification_token: string | null;
  is_primary: boolean;
  last_verification_error: string | null;
}

export function DomainsManager({ initial }: { initial: Domain[] }) {
  const router = useRouter();
  const [domains, setDomains] = useState(initial);
  const [hostname, setHostname] = useState('');
  const [pending, setPending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function refresh() {
    const res = await fetch('/api/v1/tenant/domains');
    const body = await res.json();
    setDomains(body.data);
  }

  async function addDomain(e: React.FormEvent) {
    e.preventDefault();
    setPending('add');
    setError(null);
    try {
      const res = await fetch('/api/v1/tenant/domains', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ hostname }),
      });
      const body = await res.json();
      if (!res.ok) {
        setError(body?.errors?.hostname?.[0] ?? 'Could not add domain.');
        return;
      }
      setHostname('');
      await refresh();
      router.refresh();
    } finally {
      setPending(null);
    }
  }

  async function verify(id: number) {
    setPending(`verify-${id}`);
    try {
      await fetch(`/api/v1/tenant/domains/${id}/verify`, { method: 'POST' });
      await refresh();
    } finally {
      setPending(null);
    }
  }

  async function makePrimary(id: number) {
    setPending(`primary-${id}`);
    try {
      await fetch(`/api/v1/tenant/domains/${id}/make-primary`, { method: 'POST' });
      await refresh();
      router.refresh();
    } finally {
      setPending(null);
    }
  }

  async function remove(id: number) {
    setPending(`remove-${id}`);
    try {
      await fetch(`/api/v1/tenant/domains/${id}`, { method: 'DELETE' });
      await refresh();
    } finally {
      setPending(null);
    }
  }

  return (
    <div className="space-y-6">
      <form onSubmit={addDomain} className="flex flex-wrap items-end gap-3">
        <div className="flex-1 min-w-[220px]">
          <label htmlFor="hostname" className="block text-sm font-medium text-neutral-700">
            Add a custom subdomain or domain
          </label>
          <input
            id="hostname"
            placeholder="academy.yourbrand.com"
            value={hostname}
            onChange={(e) => setHostname(e.target.value)}
            className="mt-1 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-[var(--tenant-primary)] focus:outline-none"
          />
        </div>
        <button
          type="submit"
          disabled={pending === 'add' || !hostname}
          className="rounded-md bg-[var(--tenant-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
        >
          Add domain
        </button>
      </form>
      {error && <p className="text-sm text-red-600">{error}</p>}

      <ul className="divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-white">
        {domains.map((domain) => (
          <li key={domain.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div>
              <p className="text-sm font-medium text-neutral-900">
                {domain.hostname}{' '}
                {domain.is_primary && (
                  <span className="ml-1 rounded-full bg-[var(--tenant-accent)]/15 px-2 py-0.5 text-xs font-semibold text-[var(--tenant-accent)]">
                    Primary
                  </span>
                )}
              </p>
              <p className="mt-0.5 text-xs text-neutral-500">
                {domain.domain_type} &middot; {domain.verification_status}
                {domain.verification_token && domain.verification_status !== 'verified' && (
                  <> &middot; TXT record: _naitalk-verify.{domain.hostname} = {domain.verification_token}</>
                )}
              </p>
              {domain.last_verification_error && (
                <p className="mt-0.5 text-xs text-red-600">{domain.last_verification_error}</p>
              )}
            </div>
            <div className="flex gap-2 text-xs font-medium">
              {domain.verification_status !== 'verified' && (
                <button
                  onClick={() => verify(domain.id)}
                  disabled={pending === `verify-${domain.id}`}
                  className="rounded-md border border-neutral-300 px-3 py-1.5 hover:bg-neutral-50"
                >
                  Verify
                </button>
              )}
              {domain.verification_status === 'verified' && !domain.is_primary && (
                <button
                  onClick={() => makePrimary(domain.id)}
                  disabled={pending === `primary-${domain.id}`}
                  className="rounded-md border border-neutral-300 px-3 py-1.5 hover:bg-neutral-50"
                >
                  Make primary
                </button>
              )}
              {!domain.is_primary && (
                <button
                  onClick={() => remove(domain.id)}
                  disabled={pending === `remove-${domain.id}`}
                  className="rounded-md border border-red-200 px-3 py-1.5 text-red-600 hover:bg-red-50"
                >
                  Remove
                </button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
