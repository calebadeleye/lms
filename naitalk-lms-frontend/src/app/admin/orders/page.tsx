import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { formatPrice } from '@/lib/learning-types';
import { RefundButton } from '@/components/admin/refund-button';

interface AdminOrder {
  id: number;
  status: string;
  currency: string;
  total_cents: number;
  provider: string;
  created_at: string;
  user: { id: number; name: string; email: string };
  items: { id: number; name: string }[];
  payment: { id: number; status: string; gross_amount_cents: number } | null;
}

const STATUS_STYLES: Record<string, string> = {
  paid: 'bg-green-100 text-green-700',
  pending: 'bg-amber-100 text-amber-700',
  failed: 'bg-red-100 text-red-700',
  refunded: 'bg-neutral-200 text-neutral-700',
  partially_refunded: 'bg-neutral-200 text-neutral-700',
  cancelled: 'bg-neutral-200 text-neutral-700',
};

export default async function AdminOrdersPage() {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('payments.view')) redirect('/dashboard');

  const orders = await apiFetch<{ data: AdminOrder[]; meta: { pagination: { total: number } } }>(
    '/api/v1/admin/orders?per_page=50'
  );

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin/orders">
      <h1 className="text-xl font-bold text-neutral-900">Orders</h1>
      <p className="mt-1 text-sm text-neutral-500">{orders.meta.pagination.total} total transactions.</p>

      <div className="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white">
        {orders.data.length === 0 ? (
          <p className="p-6 text-sm text-neutral-500">No orders yet.</p>
        ) : (
          <ul className="divide-y divide-neutral-100">
            {orders.data.map((order) => (
              <li key={order.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <div>
                  <p className="text-sm font-medium text-neutral-900">
                    {order.items.map((i) => i.name).join(', ') || `Order #${order.id}`}
                  </p>
                  <p className="mt-0.5 text-xs text-neutral-500">
                    {order.user.name} ({order.user.email}) &middot; {new Date(order.created_at).toLocaleString()} &middot;{' '}
                    {order.provider}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[order.status] ?? 'bg-neutral-100 text-neutral-600'}`}
                  >
                    {order.status.replace('_', ' ')}
                  </span>
                  <span className="text-sm font-semibold text-neutral-900">{formatPrice(order.total_cents, order.currency)}</span>
                  {me.permissions.includes('payments.refund') && order.payment?.status === 'success' && (
                    <RefundButton paymentId={order.payment.id} maxAmountCents={order.payment.gross_amount_cents} />
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </DashboardShell>
  );
}
