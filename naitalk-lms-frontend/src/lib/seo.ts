/**
 * Central SEO constants — site-wide defaults for metadata, keywords, and
 * theme colour. Static, like `branding.ts`: this app serves one
 * organization, so SEO copy is a source constant you edit and redeploy.
 */

// NEXT_PUBLIC_APP_URL is the full origin (protocol + host) this deployment
// is actually reachable at — set in .env.local per-environment (e.g.
// https://portal.hrgemscoachnetwork.com in production).
export const SITE_URL = process.env.NEXT_PUBLIC_APP_URL ?? 'http://localhost:3000';

export const SITE_NAME = 'HR GEMs Coach Network';

export const DEFAULT_TITLE = 'HR GEMs Coach Network — Find Your Career Fit';

export const DEFAULT_DESCRIPTION =
  'Discover your strengths, personality, values, and purpose with HR GEMs Coach Network — a ' +
  'community of HR professionals and coaches offering career-fit courses, coaching mentorship, ' +
  'and a supportive network for transformational growth.';

export const DEFAULT_KEYWORDS = [
  'HR GEMs Coach Network',
  'find your career fit',
  'career fit course',
  'career coaching community',
  'HR professional development',
  'human resources coaching',
  'coaching mentorship program',
  'career change coaching',
  'career discovery course',
  'Neuro-Linguistic Programming training',
  'coaching certification support',
  'personality and career assessment',
  'coach training network',
  'professional coaching community Nigeria',
];

// Sampled from the real logo (public/branding/logo.png) — see branding.ts.
export const THEME_COLOR = '#008080';
