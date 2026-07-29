import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { getTenantConfig } from '@/lib/tenant';
import { QueryProvider } from '@/components/query-provider';
import './globals.css';

const inter = Inter({ variable: '--font-inter', subsets: ['latin'] });

export async function generateMetadata(): Promise<Metadata> {
  const config = await getTenantConfig();

  if (!config) {
    // Also the fallback for hosts that intentionally have no tenant, such
    // as the NAI TALK platform admin domain — not necessarily an error.
    return { title: 'NAI TALK LMS' };
  }

  return {
    title: config.tenant.name,
    description: (config.branding?.homepage?.hero_subtitle as string | undefined) ?? config.tenant.name,
    icons: config.branding?.favicon_url ? [{ url: config.branding.favicon_url }] : undefined,
    manifest: '/manifest.webmanifest',
  };
}

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const config = await getTenantConfig();
  const branding = config?.branding;

  const themeVars = {
    '--tenant-primary': branding?.primary_color ?? '#3B0F32',
    '--tenant-secondary': branding?.secondary_color ?? '#F5B84B',
    '--tenant-accent': branding?.accent_color ?? '#E8A33D',
    '--tenant-font': branding?.font_family ?? 'var(--font-inter), system-ui, sans-serif',
  } as React.CSSProperties;

  return (
    <html lang="en" className={`${inter.variable} h-full antialiased`} style={themeVars}>
      <body className="min-h-full flex flex-col" style={{ fontFamily: 'var(--tenant-font)' }}>
        <QueryProvider>{children}</QueryProvider>
      </body>
    </html>
  );
}
