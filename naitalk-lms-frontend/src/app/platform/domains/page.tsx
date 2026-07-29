import { redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { platformNav } from '@/lib/nav';
import { GlobeIcon } from '@/components/platform/icons';

interface PlatformDomain {
  id: number;
  hostname: string;
  domain_type: string;
  verification_status: string;
  ssl_status: string;
  is_primary: boolean;
  verified_at: string | null;
  tenant: { id: string; name: string; slug: string };
}

const STATUS_STYLES: Record<string, string> = {
  verified: 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]',
  active: 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]',
  pending: 'bg-amber-500/15 text-amber-300',
  failed: 'bg-red-500/15 text-red-400',
};

export default async function PlatformDomainsPage() {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('domains.oversight')) redirect('/platform');

  const domains = await apiFetch<{ data: PlatformDomain[] }>('/api/v1/platform/domains');

  return (
    <PlatformShell navItems={platformNav} userName={me.user.name} userEmail={me.user.email} activeHref="/platform/domains">
      <h1 className="text-xl font-bold text-white">Domains</h1>
      <p className="mt-1 text-sm text-white/50">
        Every custom domain and subdomain across all tenants. Verification and SSL stay each tenant&apos;s own
        responsibility from their admin panel — this is oversight, not management.
      </p>

      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {domains.data.map((domain) => (
          <div key={domain.id} className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
            <div className="flex items-start justify-between">
              <span className="grid h-11 w-11 place-items-center rounded-xl bg-[var(--naitalk-green)]/15 text-[var(--naitalk-green)]">
                <GlobeIcon className="h-5 w-5" />
              </span>
              {domain.is_primary && (
                <span className="rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white/70">Primary</span>
              )}
            </div>

            <p className="mt-3 truncate font-semibold text-white">{domain.hostname}</p>
            <p className="mt-0.5 text-xs text-white/40">{domain.tenant.name}</p>

            <div className="mt-4 flex flex-wrap gap-2">
              <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[domain.verification_status] ?? 'bg-white/10 text-white/60'}`}>
                {domain.verification_status}
              </span>
              <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[domain.ssl_status] ?? 'bg-white/10 text-white/60'}`}>
                SSL {domain.ssl_status}
              </span>
              <span className="rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium capitalize text-white/60">
                {domain.domain_type.replace('_', ' ')}
              </span>
            </div>

            {domain.verified_at && (
              <p className="mt-3 text-xs text-white/40">Verified {new Date(domain.verified_at).toLocaleDateString()}</p>
            )}
          </div>
        ))}
        {domains.data.length === 0 && <p className="text-sm text-white/40">No domains yet.</p>}
      </div>
    </PlatformShell>
  );
}
