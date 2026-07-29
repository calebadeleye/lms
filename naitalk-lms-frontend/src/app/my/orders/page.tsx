import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { studentNav } from '@/lib/nav';
import { formatPrice } from '@/lib/learning-types';

interface OrderRecord {
  id: number;
  status: string;
  currency: string;
  total_cents: number;
  created_at: string;
  items: { id: number; name: string; unit_price_cents: number; quantity: number }[];
}

const STATUS_STYLES: Record<string, string> = {
  paid: 'bg-green-100 text-green-700',
  pending: 'bg-amber-100 text-amber-700',
  failed: 'bg-red-100 text-red-700',
  refunded: 'bg-neutral-200 text-neutral-700',
  partially_refunded: 'bg-neutral-200 text-neutral-700',
  cancelled: 'bg-neutral-200 text-neutral-700',
};

export default async function MyOrdersPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  const orders = await apiFetch<{ data: OrderRecord[] }>('/api/v1/my/orders');

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={studentNav} userName={me.user.name} activeHref="/my/orders">
      <h1 className="text-xl font-bold text-neutral-900">My Orders</h1>

      <div className="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        {orders.data.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">You haven&apos;t placed any orders yet.</p>
        ) : (
          <ul className="divide-y divide-neutral-100">
            {orders.data.map((order) => (
              <li key={order.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                  <p className="text-sm font-medium text-neutral-900">
                    {order.items.map((i) => i.name).join(', ') || `Order #${order.id}`}
                  </p>
                  <p className="mt-0.5 text-xs text-neutral-500">{new Date(order.created_at).toLocaleString()}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[order.status] ?? 'bg-neutral-100 text-neutral-600'}`}
                  >
                    {order.status.replace('_', ' ')}
                  </span>
                  <span className="text-sm font-semibold text-neutral-900">
                    {formatPrice(order.total_cents, order.currency)}
                  </span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </DashboardShell>
  );
}
