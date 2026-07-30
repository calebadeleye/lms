import type { NavItem } from '@/components/dashboard-shell';

export const studentNav: NavItem[] = [
  { href: '/dashboard', label: 'Dashboard' },
  { href: '/my/courses', label: 'My Courses' },
  { href: '/my/bookings', label: 'Coaching' },
  { href: '/membership', label: 'Membership' },
  { href: '/my/orders', label: 'Orders' },
  { href: '/my/certificates', label: 'Certificates' },
  { href: '/account', label: 'Account Settings' },
];

export const adminNav: NavItem[] = [
  { href: '/admin', label: 'Dashboard' },
  { href: '/admin/applications', label: 'Membership Applications' },
  { href: '/admin/users', label: 'Users' },
  { href: '/admin/students', label: 'Students' },
  { href: '/admin/courses', label: 'Courses' },
  { href: '/admin/coaching', label: 'Coaching' },
  { href: '/admin/memberships', label: 'Membership Plans' },
  { href: '/admin/orders', label: 'Orders' },
  { href: '/admin/certificates', label: 'Certificates' },
  { href: '/admin/payments', label: 'Payment Gateway' },
  { href: '/admin/testimonials', label: 'Testimonials' },
  { href: '/coming-soon?feature=Reports', label: 'Reports' },
];
