import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { AuthCard } from '@/components/auth-card';
import { LoginForm } from '@/components/login-form';

export default async function LoginPage() {
  const config = await getTenantConfig();
  if (!config) notFound();

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Welcome back"
      subtitle={`Sign in to ${config.tenant.name}`}
    >
      <LoginForm />
    </AuthCard>
  );
}
