import type { NavItem } from '@/components/dashboard-shell';
import type { NavItem as PlatformNavItem } from '@/components/platform-shell';
import {
  UsersIcon,
  BookIcon,
  CreditCardIcon,
  GlobeIcon,
  HeadsetIcon,
  DownloadIcon,
  ShieldIcon,
} from '@/components/platform/icons';

export const studentNav: NavItem[] = [
  { href: '/dashboard', label: 'Dashboard' },
  { href: '/my/courses', label: 'My Courses' },
  { href: '/my/bookings', label: 'Coaching' },
  { href: '/membership', label: 'Membership' },
  { href: '/my/orders', label: 'Orders' },
  { href: '/my/certificates', label: 'Certificates' },
  { href: '/account', label: 'Account Settings' },
];

export const platformNav: PlatformNavItem[] = [
  { href: '/platform', label: 'Tenants', icon: UsersIcon },
  { href: '/platform/plans', label: 'Plans', icon: BookIcon },
  { href: '/platform/subscriptions', label: 'Subscriptions', icon: CreditCardIcon },
  { href: '/platform/domains', label: 'Domains', icon: GlobeIcon },
  { href: '/platform/managed-payments', label: 'Managed Payments', icon: CreditCardIcon },
  { href: '/coming-soon?feature=Support%20Impersonation', label: 'Support Impersonation', icon: HeadsetIcon },
  { href: '/coming-soon?feature=Export%20Jobs', label: 'Export Jobs', icon: DownloadIcon },
  { href: '/coming-soon?feature=Audit%20Logs', label: 'Audit Logs', icon: ShieldIcon },
];

export const tenantAdminNav: NavItem[] = [
  { href: '/admin', label: 'Dashboard' },
  { href: '/admin/users', label: 'Users' },
  { href: '/admin/students', label: 'Students' },
  { href: '/admin/courses', label: 'Courses' },
  { href: '/admin/coaching', label: 'Coaching' },
  { href: '/admin/memberships', label: 'Membership Plans' },
  { href: '/admin/orders', label: 'Orders' },
  { href: '/admin/certificates', label: 'Certificates' },
  { href: '/admin/payments', label: 'Payment Gateway' },
  { href: '/admin/branding', label: 'Branding' },
  { href: '/admin/testimonials', label: 'Testimonials' },
  { href: '/admin/domains', label: 'Custom Domains' },
  { href: '/admin/exports', label: 'Data Export' },
  { href: '/coming-soon?feature=Reports', label: 'Reports' },
];
