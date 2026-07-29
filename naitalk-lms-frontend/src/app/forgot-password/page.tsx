import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { AuthCard } from '@/components/auth-card';
import { ForgotPasswordForm } from '@/components/forgot-password-form';

export default async function ForgotPasswordPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Reset your password"
      subtitle="We'll email you a reset link"
    >
      <ForgotPasswordForm />
    </AuthCard>
  );
}
