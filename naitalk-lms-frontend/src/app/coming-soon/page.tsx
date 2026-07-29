import Link from 'next/link';
import { getTenantConfig } from '@/lib/tenant';
import { getOptionalUser } from '@/lib/auth-server';
import { notFound } from 'next/navigation';
import { SiteHeader } from '@/components/site-header';
import { SiteFooter } from '@/components/site-footer';

/**
 * Honest placeholder for modules that ship in later phases (courses,
 * memberships, coaching, resources, about) rather than a fake page with
 * mock data. Phase 2/3 replace this with the real feature.
 */
export default async function ComingSoonPage({
  searchParams,
}: {
  searchParams: Promise<{ feature?: string }>;
}) {
  const config = await getTenantConfig();
  if (!config) notFound();

  const user = await getOptionalUser();
  const { feature } = await searchParams;

  return (
    <div className="flex min-h-full flex-col">
      <SiteHeader tenantName={config.tenant.name} logoUrl={config.branding?.logo_url ?? null} isAuthenticated={user !== null} />
      <main className="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-24 text-center">
        <h1 className="text-2xl font-semibold text-neutral-900">{feature ?? 'This feature'} is coming soon</h1>
        <p className="max-w-md text-neutral-600">
          {config.tenant.name} is being built out in phases. This part of the platform isn&apos;t live yet.
        </p>
        <Link href="/" className="mt-2 text-sm font-medium text-[var(--tenant-primary)] underline">
          Back to homepage
        </Link>
      </main>
      <SiteFooter tenantName={config.tenant.name} />
    </div>
  );
}
