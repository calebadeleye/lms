'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import type { SubscribedTenant, TenantSubscription } from '@/lib/platform-subscription-types';
import { STATUS_STYLES } from '@/lib/platform-subscription-types';
import type { PlatformPlan } from '@/lib/platform-plan-types';
import { formatPlanPrice } from '@/lib/platform-plan-format';
import { SubscriptionFormModal } from '@/components/platform/subscription-form-modal';
import { ComplimentaryModal } from '@/components/platform/complimentary-modal';
import { BuildingIcon, PencilIcon, GiftIcon, CalendarIcon } from '@/components/platform/icons';

export function PlatformSubscriptionsManager({
  initialTenants,
  plans,
  canGrantComplimentary,
}: {
  initialTenants: SubscribedTenant[];
  plans: PlatformPlan[];
  canGrantComplimentary: boolean;
}) {
  const router = useRouter();
  const [tenants, setTenants] = useState(initialTenants);
  const [editingTenant, setEditingTenant] = useState<SubscribedTenant | null>(null);
  const [complimentaryTenant, setComplimentaryTenant] = useState<SubscribedTenant | null>(null);

  function applyUpdate(tenantId: string, subscription: TenantSubscription) {
    setTenants((prev) => prev.map((t) => (t.id === tenantId ? { ...t, subscriptions: [subscription] } : t)));
    setEditingTenant(null);
    setComplimentaryTenant(null);
    router.refresh();
  }

  return (
    <div>
      <div>
        <h1 className="text-xl font-bold text-white">Subscriptions</h1>
        <p className="mt-1 text-sm text-white/50">
          Every tenant&apos;s current plan and billing status. Changes here take effect immediately — no invoice is
          generated or altered.
        </p>
      </div>

      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {tenants.map((tenant) => {
          const sub = tenant.subscriptions?.[0];
          const hostname = tenant.domains?.find((d) => d.is_primary)?.hostname ?? tenant.slug;

          return (
            <div
              key={tenant.id}
              className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl transition-colors hover:bg-white/[0.06]"
            >
              <div className="flex items-start justify-between">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-[var(--naitalk-green)]/15 text-[var(--naitalk-green)]">
                  <BuildingIcon className="h-5 w-5" />
                </span>
                {sub && (
                  <span className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[sub.status] ?? 'bg-white/10 text-white/60'}`}>
                    {sub.status.replace('_', ' ')}
                  </span>
                )}
              </div>

              <h2 className="mt-3 font-semibold text-white">{tenant.name}</h2>
              <p className="mt-0.5 truncate text-xs text-white/40">{hostname}</p>

              {sub ? (
                <>
                  <p className="mt-3 text-lg font-bold text-white">
                    {sub.plan.name}
                    <span className="ml-1.5 text-sm font-normal text-white/50">
                      {formatPlanPrice(sub.plan.price_cents, sub.plan.currency)}
                    </span>
                  </p>
                  <div className="mt-3 space-y-1.5 text-xs text-white/60">
                    {sub.status === 'complimentary' ? (
                      <div className="flex items-center gap-1.5">
                        <GiftIcon className="h-3.5 w-3.5 text-purple-300" />
                        {sub.complimentary_reason}
                        {sub.complimentary_until && ` · until ${new Date(sub.complimentary_until).toLocaleDateString()}`}
                      </div>
                    ) : (
                      sub.current_period_end && (
                        <div className="flex items-center gap-1.5">
                          <CalendarIcon className="h-3.5 w-3.5 text-white/40" />
                          Renews {new Date(sub.current_period_end).toLocaleDateString()}
                        </div>
                      )
                    )}
                  </div>
                </>
              ) : (
                <p className="mt-3 text-sm text-white/40">No subscription record yet.</p>
              )}

              <div className="mt-4 flex items-center gap-2 border-t border-white/10 pt-4">
                <button
                  onClick={() => setEditingTenant(tenant)}
                  className="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-[var(--naitalk-green)]/20 py-1.5 text-xs font-medium text-[var(--naitalk-green)] hover:bg-[var(--naitalk-green)]/30"
                >
                  <PencilIcon className="h-3.5 w-3.5" />
                  Change Plan
                </button>
                {canGrantComplimentary && (
                  <button
                    onClick={() => setComplimentaryTenant(tenant)}
                    className="flex flex-1 items-center justify-center gap-1.5 rounded-full border border-purple-400/30 py-1.5 text-xs font-medium text-purple-300 hover:bg-purple-500/10"
                  >
                    <GiftIcon className="h-3.5 w-3.5" />
                    Comp
                  </button>
                )}
              </div>
            </div>
          );
        })}
        {tenants.length === 0 && <p className="text-sm text-white/40">No tenants yet.</p>}
      </div>

      {editingTenant && (
        <SubscriptionFormModal
          tenant={editingTenant}
          plans={plans}
          onClose={() => setEditingTenant(null)}
          onSaved={(sub) => applyUpdate(editingTenant.id, sub)}
        />
      )}
      {complimentaryTenant && (
        <ComplimentaryModal
          tenant={complimentaryTenant}
          plans={plans}
          onClose={() => setComplimentaryTenant(null)}
          onSaved={(sub) => applyUpdate(complimentaryTenant.id, sub)}
        />
      )}
    </div>
  );
}
