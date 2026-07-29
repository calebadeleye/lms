import 'server-only';
import { headers } from 'next/headers';
import { cache } from 'react';

export interface TenantBranding {
  logo_url: string | null;
  favicon_url: string | null;
  hero_image_url: string | null;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  font_family: string;
  homepage: Record<string, unknown> | null;
  contact: Record<string, unknown> | null;
  social: Record<string, unknown> | null;
  email_sender_name: string | null;
  pwa: { name: string; theme_color: string; icon_url: string | null };
}

export interface Testimonial {
  id: number;
  quote: string;
  author: string;
}

export interface TenantConfig {
  tenant: { id: string; slug: string; name: string };
  branding: TenantBranding | null;
  domain: { primary_hostname: string | null };
  testimonials: Testimonial[];
}

/** Strips a port suffix so "hrgems.naitalk-lms.test:3000" and
 * "hrgems.naitalk-lms.test" resolve to the same tenant hostname. */
export async function getCurrentHostname(): Promise<string> {
  const headerList = await headers();
  const host = headerList.get('host') ?? process.env.NEUTRAL_PLATFORM_DOMAIN ?? 'localhost';
  return host.replace(/:\d+$/, '').toLowerCase();
}

/**
 * Resolves the current tenant's public config from the backend, keyed by
 * hostname. Memoized per request via React's cache() so every Server
 * Component in the tree can call this without triggering duplicate fetches.
 * Returns null for an unknown hostname — callers render a "not found" page
 * rather than falling through to any tenant.
 */
export const getTenantConfig = cache(async (): Promise<TenantConfig | null> => {
  const hostname = await getCurrentHostname();

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/tenant-config`, {
    headers: {
      'X-Tenant-Hostname': hostname,
      'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
    },
    // Deliberately uncached here: Next's fetch cache key semantics around
    // header-varying requests aren't something to gamble tenant isolation
    // on. The backend's TenantConfigController already caches this
    // response server-side, correctly keyed per tenant — that's the layer
    // that should own this tradeoff, not an ambiguous cross-tenant cache
    // entry on the frontend.
    cache: 'no-store',
  });

  if (!response.ok) {
    return null;
  }

  const body = await response.json();
  return body.data as TenantConfig;
});
