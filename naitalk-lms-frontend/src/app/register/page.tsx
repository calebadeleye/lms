import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { AuthCard } from '@/components/auth-card';
import { RegisterForm } from '@/components/register-form';

export default async function RegisterPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Create your account"
      subtitle={`Join ${config.tenant.name}`}
    >
      <RegisterForm />
    </AuthCard>
  );
}
