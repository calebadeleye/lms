import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { BRANDING } from '@/lib/branding';
import { QueryProvider } from '@/components/query-provider';
import './globals.css';

const inter = Inter({ variable: '--font-inter', subsets: ['latin'] });

export async function generateMetadata(): Promise<Metadata> {
  const config = BRANDING;

  return {
    title: config.tenant.name,
    description: (config.branding.homepage?.hero_subtitle as string | undefined) ?? config.tenant.name,
    icons: config.branding.favicon_url ? [{ url: config.branding.favicon_url }] : undefined,
    manifest: '/manifest.webmanifest',
  };
}

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const { branding } = BRANDING;

  const themeVars = {
    '--brand-primary': branding.primary_color,
    '--brand-secondary': branding.secondary_color,
    '--brand-accent': branding.accent_color,
    '--brand-font': branding.font_family,
  } as React.CSSProperties;

  return (
    <html lang="en" className={`${inter.variable} h-full antialiased`} style={themeVars}>
      <body className="min-h-full flex flex-col" style={{ fontFamily: 'var(--brand-font)' }}>
        <QueryProvider>{children}</QueryProvider>
      </body>
    </html>
  );
}
