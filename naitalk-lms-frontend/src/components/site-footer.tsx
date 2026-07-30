import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { NewsletterForm } from '@/components/home/newsletter-form';
import { FacebookIcon, LinkedInIcon, InstagramIcon, YoutubeIcon } from '@/components/social-icons';

const SOCIAL_ICONS = {
  facebook: FacebookIcon,
  linkedin: LinkedInIcon,
  instagram: InstagramIcon,
  youtube: YoutubeIcon,
} as const;

export function SiteFooter({ tenantName }: { tenantName: string }) {
  const { footer } = HOME_CONTENT;

  return (
    <footer className="bg-[var(--brand-primary)] text-white">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div>
          <p className="text-sm font-bold">{tenantName}</p>
          <p className="mt-1 text-xs text-white/70">Coaching, learning, and community for Change Agents in the Work of Now.</p>
        </div>

        <div>
          <p className="text-sm font-semibold">Quick Links</p>
          <ul className="mt-3 space-y-2 text-sm text-white/80">
            {footer.quickLinks.map((link) => (
              <li key={link.href}>
                <Link href={link.href} className="hover:text-white">
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <p className="text-sm font-semibold">Connect With Us</p>
          <div className="mt-3 flex gap-3">
            {footer.social.map((item) => {
              const Icon = SOCIAL_ICONS[item.platform];
              return (
                <a
                  key={item.platform}
                  href={item.href}
                  aria-label={item.platform}
                  className="grid h-9 w-9 place-items-center rounded-full bg-white/10 hover:bg-white/20"
                >
                  <Icon className="h-4 w-4" />
                </a>
              );
            })}
          </div>
          <p className="mt-4 text-sm text-white/80">{footer.contactEmail}</p>
        </div>

        <div>
          <p className="text-sm font-semibold">Join Our Community</p>
          <p className="mt-1 text-xs text-white/70">Be the first to know about upcoming programs, events, and resources.</p>
          <div className="mt-3">
            <NewsletterForm />
          </div>
        </div>
      </div>

      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-white/60 sm:px-6">
          <p>
            &copy; {new Date().getFullYear()} {tenantName}. All rights reserved.
          </p>
          <div className="flex gap-4">
            {footer.legalLinks.map((link) => (
              <Link key={link.href} href={link.href} className="hover:text-white">
                {link.label}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </footer>
  );
}
