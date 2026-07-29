export type SubscriptionStatus =
  | 'trialing'
  | 'active'
  | 'grace_period'
  | 'past_due'
  | 'suspended'
  | 'cancelled'
  | 'complimentary';

export interface TenantSubscription {
  id: number;
  tenant_id: string;
  plan_id: number;
  status: SubscriptionStatus;
  current_period_start: string | null;
  current_period_end: string | null;
  grace_period_ends_at: string | null;
  cancel_at_period_end: boolean;
  cancelled_at: string | null;
  complimentary_until: string | null;
  complimentary_reason: string | null;
  plan: { id: number; name: string; price_cents: number; currency: string; billing_period: string };
}

export interface SubscribedTenant {
  id: string;
  name: string;
  slug: string;
  status: string;
  domains?: { hostname: string; is_primary: boolean }[];
  subscriptions?: TenantSubscription[];
}

export const STATUS_STYLES: Record<string, string> = {
  trialing: 'bg-sky-500/15 text-sky-300',
  active: 'bg-[var(--naitalk-green)]/20 text-[var(--naitalk-green)]',
  grace_period: 'bg-amber-500/15 text-amber-300',
  past_due: 'bg-orange-500/15 text-orange-300',
  suspended: 'bg-red-500/15 text-red-400',
  cancelled: 'bg-white/10 text-white/50',
  complimentary: 'bg-purple-500/15 text-purple-300',
};
