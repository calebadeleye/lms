export interface MembershipPlan {
  id: number;
  name: string;
  slug: string;
  billing_period: 'monthly' | 'annual';
  price_cents: number;
  currency: string;
  benefits: string[] | null;
  is_active: boolean;
}

export interface MySubscription {
  id: number;
  status: string;
  current_period_start: string;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
  plan: MembershipPlan;
}
