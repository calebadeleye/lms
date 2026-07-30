import Link from 'next/link';
import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';

const SECTIONS: { permission: string; href: string; title: string; description: string }[] = [
  { permission: 'members.approve', href: '/admin/applications', title: 'Membership Applications', description: 'Review and approve pending join requests.' },
  { permission: 'users.manage', href: '/admin/users', title: 'Users', description: 'Invite staff and manage admin access.' },
  { permission: 'students.manage', href: '/admin/students', title: 'Students', description: 'Your learner roster, enrolments, and completion.' },
  { permission: 'courses.update', href: '/admin/courses', title: 'Courses', description: 'Build and publish your course catalogue.' },
  { permission: 'coaching.manage', href: '/admin/coaching', title: 'Coaching', description: 'Manage coaches, availability, and sessions.' },
  { permission: 'memberships.manage', href: '/admin/memberships', title: 'Membership Plans', description: 'Create and manage membership pricing.' },
  { permission: 'payments.view', href: '/admin/orders', title: 'Orders', description: 'View every transaction across your academy.' },
  { permission: 'payment_gateway.manage', href: '/admin/payments', title: 'Payment Gateway', description: 'Connect Paystack or Flutterwave.' },
  { permission: 'certificates.issue', href: '/admin/certificates', title: 'Certificates', description: 'View and revoke issued certificates.' },
  { permission: 'settings.manage', href: '/admin/testimonials', title: 'Testimonials', description: 'Manage homepage testimonials.' },
];

export default async function AdminDashboardPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (me.permissions.length === 0) redirect('/dashboard');

  const visibleSections = SECTIONS.filter((section) => me.permissions.includes(section.permission));

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={adminNav} userName={me.user.name} activeHref="/admin">
      <h1 className="text-xl font-bold text-neutral-900">Admin Dashboard</h1>
      <p className="mt-1 text-sm text-neutral-500">
        {config.tenant.name} administration &middot; signed in as <strong>{me.role?.name}</strong>
      </p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {visibleSections.map((section) => (
          <Link
            key={section.href}
            href={section.href}
            className="rounded-xl border border-neutral-200 bg-white p-5 hover:border-[var(--brand-primary)] hover:shadow-sm"
          >
            <h2 className="text-sm font-semibold text-neutral-900">{section.title}</h2>
            <p className="mt-1 text-sm text-neutral-500">{section.description}</p>
          </Link>
        ))}
        {visibleSections.length === 0 && (
          <p className="col-span-full text-sm text-neutral-500">
            Your role ({me.role?.name}) doesn&apos;t have access to any admin sections yet.
          </p>
        )}
      </div>
    </DashboardShell>
  );
}
