import Link from 'next/link';
import { formatPlanPrice } from '@/lib/platform-plan-format';

export interface UsageMetric {
  key: string;
  label: string;
  used: number;
  limit: number;
  unit: string | null;
  percent: number;
  at_limit: boolean;
}

export interface TenantSubscriptionData {
  plan: { code: string; name: string; billing_period: string; price_cents: number; currency: string } | null;
  subscription: { status: string; current_period_end: string | null; complimentary_until: string | null } | null;
  usage: UsageMetric[];
}

const STATUS_LABELS: Record<string, string> = {
  trialing: 'Trial',
  active: 'Active',
  grace_period: 'Grace period',
  past_due: 'Past due',
  suspended: 'Suspended',
  cancelled: 'Cancelled',
  complimentary: 'Complimentary',
};

function formatUsageValue(value: number, unit: string | null): string {
  if (unit === 'GB') return `${value.toFixed(2)} GB`;
  return value.toLocaleString();
}

export function PlanUsageDashboard({ data }: { data: TenantSubscriptionData }) {
  const { plan, subscription, usage } = data;

  return (
    <div className="space-y-6">
      <div className="rounded-xl border border-neutral-200 bg-white p-6">
        {plan ? (
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-neutral-400">Current plan</p>
              <h2 className="mt-1 text-2xl font-bold text-neutral-900">{plan.name}</h2>
              <p className="mt-1 text-sm text-neutral-500">
                {formatPlanPrice(plan.price_cents, plan.currency)}
                {plan.price_cents > 0 && plan.billing_period !== 'custom' && (
                  <span> / {plan.billing_period === 'annual' ? 'year' : 'month'}</span>
                )}
              </p>
            </div>
            {subscription && (
              <span className="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">
                {STATUS_LABELS[subscription.status] ?? subscription.status}
              </span>
            )}
          </div>
        ) : (
          <p className="text-sm text-neutral-500">No active subscription on this account yet.</p>
        )}
      </div>

      <div className="rounded-xl border border-neutral-200 bg-white p-6">
        <h3 className="text-sm font-semibold text-neutral-900">Usage vs. plan limits</h3>
        <p className="mt-1 text-xs text-neutral-500">Updates in real time as your team grows.</p>

        <div className="mt-5 space-y-5">
          {usage.map((metric) => (
            <div key={metric.key}>
              <div className="flex items-baseline justify-between">
                <span className="text-sm font-medium text-neutral-700">{metric.label}</span>
                <span className={`text-xs font-semibold ${metric.at_limit ? 'text-red-600' : 'text-neutral-500'}`}>
                  {formatUsageValue(metric.used, metric.unit)} / {formatUsageValue(metric.limit, metric.unit)}
                </span>
              </div>
              <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                <div
                  className={`h-full rounded-full ${
                    metric.at_limit ? 'bg-red-500' : metric.percent >= 80 ? 'bg-amber-500' : 'bg-[var(--tenant-accent)]'
                  }`}
                  style={{ width: `${metric.percent}%` }}
                />
              </div>
              {metric.at_limit && (
                <p className="mt-1 text-xs text-red-600">Limit reached — upgrade your plan to add more.</p>
              )}
            </div>
          ))}
        </div>
      </div>

      <p className="text-xs text-neutral-400">
        Want more room to grow? Contact NAI TALK to talk about upgrading your plan.{' '}
        <Link href="mailto:info@naitalk.com" className="font-medium underline">
          info@naitalk.com
        </Link>
      </p>
    </div>
  );
}
