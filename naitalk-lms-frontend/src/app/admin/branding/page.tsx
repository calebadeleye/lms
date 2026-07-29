import { notFound, redirect } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { requireUser } from '@/lib/auth-server';
import { apiFetch } from '@/lib/api-server';
import { DashboardShell } from '@/components/dashboard-shell';
import { tenantAdminNav } from '@/lib/nav';
import { BrandingForm } from '@/components/branding-form';

interface BrandingRecord {
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  email_sender_name: string | null;
}

export default async function BrandingPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  const me = await requireUser();
  if (!me.permissions.includes('branding.manage')) redirect('/dashboard');

  const branding = await apiFetch<{ data: BrandingRecord }>('/api/v1/tenant/branding');

  return (
    <DashboardShell
      tenantName={config.tenant.name}
      navItems={tenantAdminNav}
      userName={me.user.name}
      activeHref="/admin/branding"
    >
      <h1 className="text-xl font-bold text-neutral-900">Branding</h1>
      <p className="mt-1 text-sm text-neutral-500">Changes apply immediately across the whole site.</p>

      <div className="mt-6 max-w-xl rounded-xl border border-neutral-200 bg-white p-6">
        <BrandingForm
          initial={branding.data}
          logoUrl={config.branding?.logo_url ?? null}
          faviconUrl={config.branding?.favicon_url ?? null}
          heroImageUrl={config.branding?.hero_image_url ?? null}
        />
      </div>
    </DashboardShell>
  );
}
