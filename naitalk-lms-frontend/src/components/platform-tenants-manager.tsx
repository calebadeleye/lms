'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';

interface Plan {
  id: number;
  code: string;
  name: string;
}

interface Tenant {
  id: string;
  name: string;
  slug: string;
  status: string;
  domains?: { hostname: string; is_primary: boolean }[];
  subscriptions?: { status: string; plan?: { name: string } }[];
}

export function PlatformTenantsManager({ initialTenants, plans }: { initialTenants: Tenant[]; plans: Plan[] }) {
  const router = useRouter();
  const [tenants, setTenants] = useState(initialTenants);
  const [name, setName] = useState('');
  const [ownerEmail, setOwnerEmail] = useState('');
  const [planId, setPlanId] = useState<string>(plans[0]?.id.toString() ?? '');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function createTenant(e: React.FormEvent) {
    e.preventDefault();
    setPending(true);
    setError(null);

    try {
      const res = await fetch('/api/v1/platform/tenants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name,
          owner_email: ownerEmail || undefined,
          plan_id: planId ? Number(planId) : undefined,
          subscription_status: 'trialing',
        }),
      });
      const body = await res.json();

      if (!res.ok) {
        setError(body?.errors?.name?.[0] ?? body?.errors?.[0]?.message ?? 'Could not create tenant.');
        return;
      }

      setTenants((prev) => [body.data, ...prev]);
      setName('');
      setOwnerEmail('');
      router.refresh();
    } finally {
      setPending(false);
    }
  }

  async function suspend(id: string) {
    const reason = window.prompt('Reason for suspension?');
    if (!reason) return;

    await fetch(`/api/v1/platform/tenants/${id}/suspend`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason }),
    });

    setTenants((prev) => prev.map((t) => (t.id === id ? { ...t, status: 'suspended' } : t)));
  }

  async function reactivate(id: string) {
    await fetch(`/api/v1/platform/tenants/${id}/reactivate`, { method: 'POST' });
    setTenants((prev) => prev.map((t) => (t.id === id ? { ...t, status: 'active' } : t)));
  }

  return (
    <div className="space-y-6">
      <form
        onSubmit={createTenant}
        className="grid gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4 backdrop-blur-xl sm:grid-cols-4"
      >
        <div className="sm:col-span-2">
          <label className="block text-xs font-medium text-white/60">Tenant name</label>
          <input
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Acme Academy"
            className="mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--naitalk-green)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-white/60">Owner email (optional)</label>
          <input
            type="email"
            value={ownerEmail}
            onChange={(e) => setOwnerEmail(e.target.value)}
            placeholder="owner@acme.com"
            className="mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-[var(--naitalk-green)] focus:outline-none"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-white/60">Plan</label>
          <select
            value={planId}
            onChange={(e) => setPlanId(e.target.value)}
            className="mt-1 w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-[var(--naitalk-green)] focus:outline-none"
          >
            {plans.map((plan) => (
              <option key={plan.id} value={plan.id} className="bg-slate-900">
                {plan.name}
              </option>
            ))}
          </select>
        </div>
        <div className="sm:col-span-4">
          {error && <p className="mb-2 text-sm text-red-400">{error}</p>}
          <button
            type="submit"
            disabled={pending || !name}
            className="rounded-full bg-[var(--naitalk-green)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
          >
            {pending ? 'Creating…' : 'Create tenant'}
          </button>
        </div>
      </form>

      <div className="overflow-x-auto rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-xl">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-white/10 text-xs uppercase text-white/40">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Domain</th>
              <th className="px-4 py-3">Plan</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-white/10">
            {tenants.map((tenant) => (
              <tr key={tenant.id}>
                <td className="px-4 py-3 font-medium text-white">{tenant.name}</td>
                <td className="px-4 py-3 text-white/60">
                  {tenant.domains?.find((d) => d.is_primary)?.hostname ?? '—'}
                </td>
                <td className="px-4 py-3 text-white/60">{tenant.subscriptions?.[0]?.plan?.name ?? '—'}</td>
                <td className="px-4 py-3">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                      tenant.status === 'active'
                        ? 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]'
                        : tenant.status === 'suspended'
                          ? 'bg-red-500/15 text-red-400'
                          : 'bg-white/10 text-white/60'
                    }`}
                  >
                    {tenant.status}
                  </span>
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-3">
                    <Link href={`/platform/tenants/${tenant.id}`} className="text-xs font-medium text-white/70 hover:underline">
                      View
                    </Link>
                    {tenant.status === 'suspended' ? (
                      <button onClick={() => reactivate(tenant.id)} className="text-xs font-medium text-white/70 hover:underline">
                        Reactivate
                      </button>
                    ) : (
                      <button onClick={() => suspend(tenant.id)} className="text-xs font-medium text-red-400 hover:underline">
                        Suspend
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {tenants.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-white/40">
                  No tenants yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
