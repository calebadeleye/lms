export interface OrderItem {
  id: number;
  itemable_type: string;
  itemable_id: number;
  name: string;
  unit_price_cents: number;
  quantity: number;
}

export interface OrderStatus {
  status: 'pending' | 'paid' | 'failed' | 'refunded' | 'partially_refunded' | 'cancelled';
  total_cents: number;
  currency: string;
  items: OrderItem[];
}

export interface PaymentConfig {
  provider: 'paystack' | 'flutterwave';
  mode: 'client_owned' | 'managed';
  public_key: string | null;
  public_key_masked: string | null;
  has_secret_configured: boolean;
  subaccount_code: string | null;
  environment: 'test' | 'live';
  commission_percent: number;
  fee_bearer: 'tenant' | 'learner' | 'platform';
  status: string;
  last_verified_at: string | null;
}
