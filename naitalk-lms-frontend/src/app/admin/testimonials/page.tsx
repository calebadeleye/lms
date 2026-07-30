import { redirect } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { adminNav } from '@/lib/nav';
import { TestimonialManager, type Testimonial } from '@/components/admin/testimonial-manager';

export default async function TestimonialsPage() {
  const config = BRANDING;
  const me = await requireUser();
  if (!me.permissions.includes('settings.manage')) redirect('/dashboard');

  const testimonials = await apiFetch<{ data: Testimonial[] }>('/api/v1/testimonials');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={adminNav}
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
