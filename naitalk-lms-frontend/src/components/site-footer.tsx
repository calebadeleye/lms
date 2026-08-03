import Link from 'next/link';
import { HOME_CONTENT } from '@/lib/home-content';
import { FacebookIcon, LinkedInIcon, InstagramIcon, YoutubeIcon } from '@/components/social-icons';

const SOCIAL_ICONS = { facebook: FacebookIcon, linkedin: LinkedInIcon, instagram: InstagramIcon, youtube: YoutubeIcon } as const;

const quickLinks = [
  { label: 'About Us', href: '/about' },
  { label: 'Courses', href: '/courses' },
  { label: 'Membership', href: '/membership' },
  { label: 'Coaching', href: '/coaching' },
  { label: 'Community', href: '/#experience' },
];

const resources = [
  { label: 'Blog', href: '/coming-soon?feature=Blog' },
  { label: 'Events', href: '/coming-soon?feature=Events' },
  { label: 'Downloads', href: '/coming-soon?feature=Downloads' },
  { label: 'FAQs', href: '/coming-soon?feature=FAQs' },
  { label: 'Contact Us', href: '/coming-soon?feature=Contact' },
];

const support = [
  { label: 'Help Centre', href: '/coming-soon?feature=Help%20Centre' },
  { label: 'Privacy Policy', href: '/coming-soon?feature=Privacy%20Policy' },
  { label: 'Terms of Service', href: '/coming-soon?feature=Terms%20of%20Service' },
  { label: 'Refund Policy', href: '/coming-soon?feature=Refund%20Policy' },
  { label: 'Cookie Policy', href: '/coming-soon?feature=Cookie%20Policy' },
];

export function SiteFooter({ tenantName }: { tenantName: string }) {
  const { footer } = HOME_CONTENT;

  return (
    <footer className="bg-[#042f33] px-5 pb-6 pt-14 text-white sm:px-7 lg:px-8">
      <div className="mx-auto grid max-w-[1100px] gap-9 md:grid-cols-[1.55fr_repeat(3,0.75fr)_1.35fr]">
        <div>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/branding/logo.png" alt={tenantName} className="h-[62px] w-auto" />
          <p className="mt-3 max-w-[230px] text-xs leading-5 text-white/68">Great. Excellent. Minds creating clarity, growth and impact.</p>
          <div className="mt-5 flex gap-2">
            {footer.social.map((item) => {
              const Icon = SOCIAL_ICONS[item.platform];
              return <a key={item.platform} href={item.href} aria-label={item.platform} className="grid h-8 w-8 place-items-center rounded-full bg-white/8 text-white/85 transition hover:bg-[#006c70]"><Icon className="h-4 w-4" /></a>;
            })}
          </div>
        </div>
        <FooterColumn title="Quick Links" links={quickLinks} />
        <FooterColumn title="Resources" links={resources} />
        <FooterColumn title="Support" links={support} />
        <div>
          <h2 className="text-sm font-bold">Contact Us</h2>
          <ul className="mt-3 space-y-3 text-xs leading-5 text-white/72">
            <li><a href={`mailto:${footer.contactEmail}`} className="flex gap-2 hover:text-white"><span className="text-[#f4b728]">✉</span>{footer.contactEmail}</a></li>
            <li className="flex gap-2"><span className="text-[#f4b728]">●</span>+234 802 345 1054</li>
            <li className="flex gap-2"><span className="text-[#f4b728]">◆</span>Lagos, Nigeria</li>
          </ul>
        </div>
      </div>
      <div className="mx-auto mt-9 flex max-w-[1100px] flex-col gap-2 border-t border-white/10 pt-4 text-[11px] text-white/55 sm:flex-row sm:items-center sm:justify-between">
        <p>© {new Date().getFullYear()} {tenantName}. All rights reserved.</p>
        <p>Made with <span className="text-[#f4b728]">♥</span> by <a href="https://naitalk.com" className="text-white/80 hover:text-white">NAI TALK</a></p>
      </div>
    </footer>
  );
}

function FooterColumn({ title, links }: { title: string; links: { label: string; href: string }[] }) {
  return (
    <div>
      <h2 className="text-sm font-bold">{title}</h2>
      <ul className="mt-3 space-y-1 text-xs leading-5 text-white/72">
        {links.map((link) => <li key={link.label}><Link href={link.href} className="hover:text-white">{link.label}</Link></li>)}
      </ul>
    </div>
  );
}
