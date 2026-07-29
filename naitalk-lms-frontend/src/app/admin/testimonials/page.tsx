import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { TestimonialManager, type Testimonial } from '@/components/admin/testimonial-manager';

export default async function TestimonialsPage() {
  const [config, me] = await Promise.all([getTenantConfig(), requireUser()]);
  if (!config) notFound();
  if (!me.permissions.includes('branding.manage')) redirect('/dashboard');

  const testimonials = await apiFetch<{ data: Testimonial[] }>('/api/v1/tenant/testimonials');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={tenantAdminNav}
      userName={me.user.name}
      activeHref="/admin/testimonials"
    >
      <h1 className="text-xl font-bold text-neutral-900">Testimonials</h1>
      <p className="mt-1 text-sm text-neutral-500">Changes apply immediately across the whole site.</p>

      <div className="mt-6 max-w-xl">
        <TestimonialManager initial={testimonials.data} />
      </div>
    </DashboardShell>
  );
}
