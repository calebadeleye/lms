import type { Metadata, Viewport } from 'next';
import { Inter, Manrope } from 'next/font/google';
import { BRANDING } from '@/lib/branding';
import { DEFAULT_DESCRIPTION, DEFAULT_KEYWORDS, DEFAULT_TITLE, SITE_NAME, SITE_URL, THEME_COLOR } from '@/lib/seo';
import { QueryProvider } from '@/components/query-provider';
import './globals.css';

const inter = Inter({ variable: '--font-inter', subsets: ['latin'] });
const manrope = Manrope({ variable: '--font-manrope', subsets: ['latin'] });

export const viewport: Viewport = {
  themeColor: THEME_COLOR,
};

export async function generateMetadata(): Promise<Metadata> {
  const config = BRANDING;

  return {
    metadataBase: new URL(SITE_URL),
    title: { default: DEFAULT_TITLE, template: `%s | ${SITE_NAME}` },
    description: DEFAULT_DESCRIPTION,
    keywords: DEFAULT_KEYWORDS,
    icons: config.branding.favicon_url ? [{ url: config.branding.favicon_url }] : undefined,
    manifest: '/manifest.webmanifest',
    robots: { index: true, follow: true },
    openGraph: {
      type: 'website',
      url: SITE_URL,
      siteName: SITE_NAME,
      title: DEFAULT_TITLE,
      description: DEFAULT_DESCRIPTION,
      locale: 'en_US',
    },
    twitter: {
      card: 'summary_large_image',
      title: DEFAULT_TITLE,
      description: DEFAULT_DESCRIPTION,
    },
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
    <html lang="en" className={`${inter.variable} ${manrope.variable} h-full antialiased`} style={themeVars}>
      <body className="min-h-full flex flex-col" style={{ fontFamily: 'var(--brand-font)' }}>
        <QueryProvider>{children}</QueryProvider>
      </body>
    </html>
  );
}
