import { redirect } from 'next/navigation';
import { requirePlatformStaff } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { PlatformShell } from '@/components/platform-shell';
import { platformNav } from '@/lib/nav';
import { CreditCardIcon } from '@/components/platform/icons';

interface ManagedPaymentConfig {
  id: number;
  provider: string;
  environment: string;
  fee_bearer: string;
  status: string;
  subaccount_code: string | null;
  effective_commission_percent: number;
  last_verified_at: string | null;
  tenant: { id: string; name: string; slug: string };
}

const STATUS_STYLES: Record<string, string> = {
  active: 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]',
  inactive: 'bg-white/10 text-white/60',
};

export default async function PlatformManagedPaymentsPage() {
  const me = await requirePlatformStaff();
  if (!me.permissions.includes('payments.manage')) redirect('/platform');

  const configs = await apiFetch<{ data: ManagedPaymentConfig[] }>('/api/v1/platform/managed-payments');

  return (
    <PlatformShell
      navItems={platformNav}
      userName={me.user.name}
      userEmail={me.user.email}
      activeHref="/platform/managed-payments"
    >
      <h1 className="text-xl font-bold text-white">Managed Payments</h1>
      <p className="mt-1 text-sm text-white/50">
        Tenants using NAI TALK&apos;s own gateway — a split-settlement subaccount collects each sale, with NAI
        TALK&apos;s commission deducted at the provider level. Client-owned gateways never appear here.
      </p>

      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {configs.data.map((config) => (
          <div key={config.id} className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl">
            <div className="flex items-start justify-between">
              <span className="grid h-11 w-11 place-items-center rounded-xl bg-[var(--naitalk-green)]/15 text-[var(--naitalk-green)]">
                <CreditCardIcon className="h-5 w-5" />
              </span>
              <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[config.status] ?? 'bg-white/10 text-white/60'}`}>
                {config.status}
              </span>
            </div>

            <p className="mt-3 font-semibold text-white">{config.tenant.name}</p>
            <p className="mt-0.5 text-xs capitalize text-white/40">
              {config.provider} · {config.environment}
            </p>

            <div className="mt-4 space-y-1.5 text-xs text-white/60">
              <div className="flex items-center justify-between">
                <span>Commission</span>
                <span className="font-medium text-white">{config.effective_commission_percent}%</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Fee bearer</span>
                <span className="font-medium capitalize text-white">{config.fee_bearer}</span>
              </div>
              <div className="flex items-center justify-between">
                <span>Subaccount</span>
                <span className="truncate font-mono text-[11px] text-white/70">{config.subaccount_code ?? '—'}</span>
              </div>
            </div>

            {config.last_verified_at && (
              <p className="mt-3 text-xs text-white/40">
                Last verified {new Date(config.last_verified_at).toLocaleDateString()}
              </p>
            )}
          </div>
        ))}
        {configs.data.length === 0 && <p className="text-sm text-white/40">No tenants on managed payments yet.</p>}
      </div>
    </PlatformShell>
  );
}
