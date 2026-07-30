import { BRANDING } from '@/lib/branding';
import { getOptionalUser } from '@/lib/auth-server';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';
import { CheckoutStatus } from '@/components/checkout-status';

/**
 * Landing page the payment provider redirects back to after checkout. The
 * provider echoes our own `reference` (Paystack: reference/trxref,
 * Flutterwave: tx_ref) verbatim — and CheckoutService builds that reference
 * as `order_{orderId}_{idempotencyKey}`, so the order id can be read straight
 * back out of it without any server-side session state.
 */
export default async function CheckoutCallbackPage({
  searchParams,
}: {
  searchParams: Promise<{ reference?: string; trxref?: string; tx_ref?: string }>;
}) {
  const config = BRANDING;

  const user = await getOptionalUser();
  const params = await searchParams;
  const reference = params.reference ?? params.trxref ?? params.tx_ref ?? '';
  const orderId = parseOrderId(reference);

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-16 sm:px-6">
        <CheckoutStatus orderId={orderId} reference={reference} isAuthenticated={user !== null} />
      </main>
      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}

function parseOrderId(reference: string): number | null {
  const match = reference.match(/^order_(\d+)_/);
  return match ? Number(match[1]) : null;
}
