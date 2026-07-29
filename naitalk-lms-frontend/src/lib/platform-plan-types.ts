export interface PlanFeature {
  feature_key: string;
  value: string;
}

export interface PlatformPlan {
  id: number;
  code: string;
  name: string;
  description: string | null;
  billing_period: 'monthly' | 'annual' | 'free' | 'trial' | 'custom';
  price_cents: number;
  currency: string;
  is_public: boolean;
  trial_days: number;
  sort_order: number;
  features: PlanFeature[];
}

/** The full set of entitlement keys every plan is expected to carry.
 * Shared between the create/edit form and the plan card so the two never
 * drift out of sync — a key added here shows up in both automatically. */
export const PLAN_LIMIT_FIELDS = [
  { key: 'max_administrators', label: 'Max administrators', icon: 'users' },
  { key: 'max_instructors', label: 'Max instructors', icon: 'graduation' },
  { key: 'max_coaches', label: 'Max coaches', icon: 'coach' },
  { key: 'max_active_students', label: 'Max active students', icon: 'users' },
  { key: 'max_published_courses', label: 'Max published courses', icon: 'book' },
  { key: 'storage_gb', label: 'Storage GB', icon: 'storage' },
  { key: 'certificate_template_limit', label: 'Certificate templates', icon: 'book' },
] as const;

export const PLAN_TOGGLE_FIELDS = [
  { key: 'certificates_enabled', label: 'Certificates' },
  { key: 'coaching_enabled', label: 'Coaching' },
  { key: 'memberships_enabled', label: 'Memberships' },
  { key: 'custom_domain_enabled', label: 'Custom domain' },
  { key: 'client_owned_gateway_enabled', label: 'Client-owned payment gateway' },
  { key: 'managed_gateway_enabled', label: 'NAI TALK managed payments' },
  { key: 'full_data_export_enabled', label: 'Full data export' },
  { key: 'api_access_enabled', label: 'API access' },
  { key: 'native_mobile_app_enabled', label: 'Native mobile app' },
  { key: 'advanced_reporting_enabled', label: 'Advanced reporting' },
] as const;

export function featuresToMap(features: PlanFeature[]): Record<string, string> {
  return Object.fromEntries(features.map((f) => [f.feature_key, f.value]));
}
