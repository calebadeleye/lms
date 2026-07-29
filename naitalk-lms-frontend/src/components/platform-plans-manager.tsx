'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { PLAN_LIMIT_FIELDS, featuresToMap, type PlatformPlan } from '@/lib/platform-plan-types';
import { formatPlanPrice } from '@/lib/platform-plan-format';
import { PlanFormModal } from '@/components/platform/plan-form-modal';
import { PlanViewModal } from '@/components/platform/plan-view-modal';
import {
  UsersIcon,
  GraduationCapIcon,
  CoachIcon,
  BookIcon,
  StorageIcon,
  GiftIcon,
  RocketIcon,
  TrendingUpIcon,
  CalendarIcon,
  BuildingIcon,
  LockIcon,
  EyeIcon,
  PencilIcon,
  DotsIcon,
  PlusIcon,
  TrashIcon,
} from '@/components/platform/icons';

const LIMIT_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  max_administrators: UsersIcon,
  max_instructors: GraduationCapIcon,
  max_coaches: CoachIcon,
  max_active_students: UsersIcon,
  max_published_courses: BookIcon,
  storage_gb: StorageIcon,
  certificate_template_limit: BookIcon,
};

function planIcon(plan: PlatformPlan, monthlyPrices: number[]): React.ComponentType<{ className?: string }> {
  if (plan.billing_period === 'free') return GiftIcon;
  if (plan.billing_period === 'annual') return CalendarIcon;
  if (plan.billing_period === 'custom' || plan.billing_period === 'trial') return BuildingIcon;
  const cheapest = Math.min(...monthlyPrices);
  return plan.price_cents === cheapest ? RocketIcon : TrendingUpIcon;
}

export function PlatformPlansManager({ initialPlans }: { initialPlans: PlatformPlan[] }) {
  const router = useRouter();
  const [plans, setPlans] = useState(initialPlans);
  const [formPlan, setFormPlan] = useState<PlatformPlan | null | 'new'>(null);
  const [viewPlan, setViewPlan] = useState<PlatformPlan | null>(null);
  const [menuOpenId, setMenuOpenId] = useState<number | null>(null);

  const monthlyPrices = plans.filter((p) => p.billing_period === 'monthly').map((p) => p.price_cents);

  function handleSaved(plan: PlatformPlan) {
    setPlans((prev) => {
      const exists = prev.some((p) => p.id === plan.id);
      return exists ? prev.map((p) => (p.id === plan.id ? plan : p)) : [...prev, plan];
    });
    setFormPlan(null);
    router.refresh();
  }

  async function deletePlan(plan: PlatformPlan) {
    if (!window.confirm(`Delete plan "${plan.name}"? Tenants already subscribed keep their current entitlements.`)) return;
    await fetch(`/api/v1/platform/plans/${plan.id}`, { method: 'DELETE' });
    setPlans((prev) => prev.filter((p) => p.id !== plan.id));
    setMenuOpenId(null);
    router.refresh();
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-white">Platform Plans</h1>
          <p className="mt-1 text-sm text-white/50">
            What NAI TALK sells to tenants. Entitlements here drive what every tenant can do — nothing is
            hard-coded in the frontend.
          </p>
        </div>
        <button
          onClick={() => setFormPlan('new')}
          className="flex items-center gap-2 rounded-full bg-[var(--naitalk-green)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
        >
          <PlusIcon className="h-4 w-4" />
          Create Plan
        </button>
      </div>

      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {plans.map((plan) => {
          const features = featuresToMap(plan.features);
          const Icon = planIcon(plan, monthlyPrices);

          return (
            <div
              key={plan.id}
              className="relative rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur-xl transition-colors hover:bg-white/[0.06]"
            >
              <div className="flex items-start justify-between">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-[var(--naitalk-green)]/15 text-[var(--naitalk-green)]">
                  <Icon className="h-5 w-5" />
                </span>
                {!plan.is_public && (
                  <span className="flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white/70">
                    <LockIcon className="h-3 w-3" />
                    Private
                  </span>
                )}
              </div>

              <h2 className="mt-3 font-semibold text-white">{plan.name}</h2>
              <p className="mt-0.5 text-2xl font-bold text-white">
                {formatPlanPrice(plan.price_cents, plan.currency)}
                {plan.price_cents > 0 && (
                  <span className="text-sm font-normal text-white/50">
                    /{plan.billing_period === 'annual' ? 'yr' : 'mo'}
                  </span>
                )}
              </p>

              <ul className="mt-4 space-y-1.5 text-xs text-white/60">
                {PLAN_LIMIT_FIELDS.map((field) => {
                  const FieldIcon = LIMIT_ICONS[field.key];
                  return (
                    <li key={field.key} className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5">
                        <FieldIcon className="h-3.5 w-3.5 text-white/40" />
                        {field.label}
                      </span>
                      <span className="font-medium text-white">{features[field.key] ?? '—'}</span>
                    </li>
                  );
                })}
              </ul>

              <div className="mt-4 flex items-center gap-2 border-t border-white/10 pt-4">
                <button
                  onClick={() => setViewPlan(plan)}
                  className="flex flex-1 items-center justify-center gap-1.5 rounded-full border border-white/15 py-1.5 text-xs font-medium text-white/80 hover:bg-white/5"
                >
                  <EyeIcon className="h-3.5 w-3.5" />
                  View
                </button>
                <button
                  onClick={() => setFormPlan(plan)}
                  className="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-[var(--naitalk-green)]/20 py-1.5 text-xs font-medium text-[var(--naitalk-green)] hover:bg-[var(--naitalk-green)]/30"
                >
                  <PencilIcon className="h-3.5 w-3.5" />
                  Edit
                </button>
                <div className="relative">
                  <button
                    onClick={() => setMenuOpenId((id) => (id === plan.id ? null : plan.id))}
                    className="grid h-7 w-7 place-items-center rounded-full border border-white/15 text-white/70 hover:bg-white/5"
                  >
                    <DotsIcon className="h-4 w-4" />
                  </button>
                  {menuOpenId === plan.id && (
                    <div className="absolute right-0 z-10 mt-1 w-36 overflow-hidden rounded-lg border border-white/10 bg-[#0b1220] shadow-xl">
                      <button
                        onClick={() => deletePlan(plan)}
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-red-400 hover:bg-white/5"
                      >
                        <TrashIcon className="h-3.5 w-3.5" />
                        Delete plan
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          );
        })}
        {plans.length === 0 && <p className="text-sm text-white/40">No plans yet — create one to get started.</p>}
      </div>

      {formPlan && (
        <PlanFormModal
          plan={formPlan === 'new' ? null : formPlan}
          onClose={() => setFormPlan(null)}
          onSaved={handleSaved}
        />
      )}
      {viewPlan && <PlanViewModal plan={viewPlan} onClose={() => setViewPlan(null)} />}
    </div>
  );
}
