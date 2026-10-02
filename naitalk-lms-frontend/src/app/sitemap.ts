import type { MetadataRoute } from 'next';
import { SITE_URL } from '@/lib/seo';

export default function sitemap(): MetadataRoute.Sitemap {
  const now = new Date();

  const routes: { path: string; changeFrequency: MetadataRoute.Sitemap[number]['changeFrequency']; priority: number }[] = [
    { path: '/', changeFrequency: 'monthly', priority: 1 },
    { path: '/about', changeFrequency: 'yearly', priority: 0.8 },
    { path: '/courses', changeFrequency: 'weekly', priority: 0.9 },
    { path: '/membership', changeFrequency: 'monthly', priority: 0.8 },
    { path: '/coaching', changeFrequency: 'weekly', priority: 0.8 },
    { path: '/testimonials', changeFrequency: 'monthly', priority: 0.6 },
    { path: '/register', changeFrequency: 'yearly', priority: 0.7 },
    { path: '/login', changeFrequency: 'yearly', priority: 0.3 },
  ];

  return routes.map(({ path, changeFrequency, priority }) => ({
    url: `${SITE_URL}${path}`,
    lastModified: now,
    changeFrequency,
    priority,
  }));
}
