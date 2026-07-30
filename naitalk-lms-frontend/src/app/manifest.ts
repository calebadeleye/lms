import type { MetadataRoute } from 'next';
import { BRANDING } from '@/lib/branding';

export default function manifest(): MetadataRoute.Manifest {
  const { branding } = BRANDING;
  const name = branding.pwa.name;

  return {
    name,
    short_name: name.length > 12 ? name.slice(0, 12) : name,
    description: `${name} — online courses and coaching`,
    start_url: '/dashboard',
    display: 'standalone',
    background_color: '#ffffff',
    theme_color: branding.pwa.theme_color,
    icons: branding.pwa.icon_url
      ? [{ src: branding.pwa.icon_url, sizes: 'any', type: 'image/png' }]
      : [{ src: '/favicon.ico', sizes: 'any', type: 'image/x-icon' }],
  };
}
