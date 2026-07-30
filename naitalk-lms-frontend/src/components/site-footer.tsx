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
  const quickLinks = [...footer.quickLinks, { label: 'Login', href: '/login' }];

  return (
    <footer className="border-t-2 border-[#d5a600] bg-[#005456] text-white">
      <div className="mx-auto grid max-w-[1120px] gap-8 px-4 py-8 sm:px-8 md:grid-cols-[1.25fr_0.75fr_1fr_1.35fr]">
        <div className="flex items-center md:border-r md:border-dotted md:border-white/30 md:pr-10">
          <div>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/branding/logo.png" alt={tenantName} className="h-16 w-auto brightness-0 invert" />
          </div>
        </div>

        <div className="md:border-r md:border-dotted md:border-white/30 md:pr-8">
          <p className="text-sm font-black">Quick Links</p>
          <ul className="mt-3 space-y-1 text-xs font-medium text-white/90">
            {quickLinks.map((link) => (
              <li key={link.href}>
                <Link href={link.href} className="hover:text-white">
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div className="md:border-r md:border-dotted md:border-white/30 md:pr-8">
          <p className="text-sm font-black">Connect With Us</p>
          <div className="mt-4 flex gap-5">
            {footer.social.map((item) => {
              const Icon = SOCIAL_ICONS[item.platform];
              return (
                <a
                  key={item.platform}
                  href={item.href}
                  aria-label={item.platform}
                  className="text-white/90 hover:text-white"
                >
                  <Icon className="h-5 w-5" />
                </a>
              );
            })}
          </div>
          <p className="mt-5 text-xs font-medium text-white/85">{footer.contactEmail}</p>
        </div>

        <div>
          <p className="text-sm font-black text-[#ffbd11]">Join Our Community</p>
          <p className="mt-2 max-w-[330px] text-xs font-medium leading-relaxed text-white/90">Be the first to know about upcoming programs, events, and resources.</p>
          <div className="mt-4">
            <NewsletterForm />
          </div>
        </div>
      </div>

      <div className="border-t border-white/10 bg-[#004749]">
        <div className="mx-auto flex max-w-[1120px] flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-white/85 sm:px-8">
          <p>
            &copy; {new Date().getFullYear()} {tenantName}. All rights reserved.
          </p>
          <div className="flex items-center gap-5">
            {footer.legalLinks.map((link) => (
              <Link key={link.href} href={link.href} className="hover:text-white">
                {link.label}
              </Link>
            ))}
            <span className="text-white/60">
              Made by{' '}
              <a href="https://naitalk.com" className="hover:text-white">
                NAI TALK
              </a>
            </span>
          </div>
        </div>
      </div>
    </footer>
  );
}
