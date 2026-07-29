import type { MetadataRoute } from 'next';
import { getTenantConfig } from '@/lib/tenant';

export default async function manifest(): Promise<MetadataRoute.Manifest> {
  const config = await getTenantConfig();
  const branding = config?.branding;
  const name = branding?.pwa.name ?? config?.tenant.name ?? 'NAI TALK LMS';

  return {
    name,
    short_name: name.length > 12 ? name.slice(0, 12) : name,
    description: `${name} — online courses and coaching`,
    start_url: '/dashboard',
    display: 'standalone',
    background_color: '#ffffff',
    theme_color: branding?.pwa.theme_color ?? branding?.primary_color ?? '#3B0F32',
    icons: branding?.pwa.icon_url
      ? [{ src: branding.pwa.icon_url, sizes: 'any', type: 'image/png' }]
      : [{ src: '/favicon.ico', sizes: 'any', type: 'image/x-icon' }],
  };
}
