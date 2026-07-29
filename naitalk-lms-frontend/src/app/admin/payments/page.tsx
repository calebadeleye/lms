import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { PaymentConfigForm } from '@/components/payment-config-form';
import type { PaymentConfig } from '@/lib/commerce-types';

export default async function PaymentSettingsPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('payment_gateway.manage')) redirect('/dashboard');

  const paymentConfig = await apiFetch<{ data: PaymentConfig | null }>('/api/v1/admin/payment-config');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={tenantAdminNav}
      userName={me.user.name}
      activeHref="/admin/payments"
    >
      <h1 className="text-xl font-bold text-neutral-900">Payment Gateway</h1>
      <p className="mt-1 text-sm text-neutral-500">
        Connect a payment provider so students can buy paid courses, memberships, and coaching sessions.
      </p>

      <div className="mt-6 max-w-2xl">
        <PaymentConfigForm initial={paymentConfig.data} />
      </div>
    </DashboardShell>
  );
}
