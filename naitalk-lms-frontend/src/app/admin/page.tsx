import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';

const SECTIONS: { permission: string; href: string; title: string; description: string }[] = [
  { permission: 'users.manage', href: '/admin/users', title: 'Users', description: 'Invite staff and manage admin access.' },
  { permission: 'students.manage', href: '/admin/students', title: 'Students', description: 'Your learner roster, enrolments, and completion.' },
  { permission: 'courses.update', href: '/admin/courses', title: 'Courses', description: 'Build and publish your course catalogue.' },
  { permission: 'coaching.manage', href: '/admin/coaching', title: 'Coaching', description: 'Manage coaches, availability, and sessions.' },
  { permission: 'memberships.manage', href: '/admin/memberships', title: 'Membership Plans', description: 'Create and manage membership pricing.' },
  { permission: 'payments.view', href: '/admin/orders', title: 'Orders', description: 'View every transaction across your academy.' },
  { permission: 'payment_gateway.manage', href: '/admin/payments', title: 'Payment Gateway', description: 'Connect Paystack or Flutterwave.' },
  { permission: 'certificates.issue', href: '/admin/certificates', title: 'Certificates', description: 'View and revoke issued certificates.' },
  { permission: 'exports.request', href: '/admin/exports', title: 'Data Export', description: "Download a full copy of your academy's data." },
  { permission: 'branding.manage', href: '/admin/branding', title: 'Branding', description: 'Customize your homepage and brand colors.' },
  { permission: 'domains.manage', href: '/admin/domains', title: 'Custom Domains', description: 'Connect your own domain.' },
];

export default async function AdminDashboardPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (me.permissions.length === 0) redirect('/dashboard');

  const visibleSections = SECTIONS.filter((section) => me.permissions.includes(section.permission));

  return (
    <DashboardShell tenantName={config.tenant.name} navItems={tenantAdminNav} userName={me.user.name} activeHref="/admin">
      <h1 className="text-xl font-bold text-neutral-900">Admin Dashboard</h1>
      <p className="mt-1 text-sm text-neutral-500">
        {config.tenant.name} tenant administration &middot; signed in as <strong>{me.role?.name}</strong>
      </p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {visibleSections.map((section) => (
          <Link
            key={section.href}
            href={section.href}
            className="rounded-xl border border-neutral-200 bg-white p-5 hover:border-[var(--tenant-primary)] hover:shadow-sm"
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
