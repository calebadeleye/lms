/**
 * Static branding for HR GEMs Coach Network — this app now serves a single
 * organization, so branding is a source constant rather than a per-tenant
 * lookup. Changing the logo, colours, or hero image means editing this file
 * and redeploying (a deliberate simplification decided over a self-serve
 * admin branding form).
 *
 * Field names deliberately mirror the old per-tenant config shape so every
 * call site that did `config.tenant.name` / `config.branding?.logo_url`
 * keeps working unchanged against this constant.
 */

export interface BrandingConfig {
  tenant: { name: string };
  branding: {
    logo_url: string | null;
    favicon_url: string | null;
    hero_image_url: string | null;
    primary_color: string;
    secondary_color: string;
    accent_color: string;
    font_family: string;
    homepage: Record<string, unknown> | null;
    email_sender_name: string;
    pwa: { name: string; theme_color: string; icon_url: string | null };
  };
  domain: { primary_hostname: string };
}

export const BRANDING: BrandingConfig = {
  tenant: { name: 'HR GEMs Coach Network' },
  branding: {
    // Real logo supplied 2026-07-30 (public/branding/logo.png). Colours were
    // sampled directly from it (see git history for the extraction) rather
    // than eyeballed: teal #008080 dominates the wordmark/icon, gold
    // #F5D908 is the accent dot. secondary_color is a lighter tint of that
    // same gold (not a separately-supplied colour) used as the far end of
    // decorative gradients. No square-crop favicon/hero asset exists yet —
    // those stay null/default until one is supplied.
    logo_url: '/branding/logo.png',
    favicon_url: '/favicon.ico',
    hero_image_url: null,
    primary_color: '#008080',
    secondary_color: '#F9E86B',
    accent_color: '#F5D908',
    font_family: 'var(--font-inter), system-ui, sans-serif',
    homepage: {
      hero_title: 'Grow with HR GEMs.',
      hero_subtitle: 'Coaching, learning, and community for Change Agents in the Work of Now.',
    },
    email_sender_name: 'HR GEMs Coach Network',
    pwa: { name: 'HR GEMs Coach Network', theme_color: '#008080', icon_url: null },
  },
  domain: { primary_hostname: (process.env.NEXT_PUBLIC_APP_HOSTNAME ?? 'hrgemscoachnetwork.com') },
};
