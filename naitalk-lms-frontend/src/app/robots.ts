import type { MetadataRoute } from 'next';
import { SITE_URL } from '@/lib/seo';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: '*',
      allow: '/',
      disallow: [
        '/dashboard',
        '/admin',
        '/account',
        '/my/',
        '/checkout',
        '/onboarding',
        '/learn/',
        '/invitations/',
        '/verify-email',
        '/reset-password',
        '/forgot-password',
        '/api/',
      ],
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
