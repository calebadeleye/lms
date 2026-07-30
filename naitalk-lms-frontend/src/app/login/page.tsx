import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { LoginForm } from '@/components/login-form';

export default async function LoginPage() {
  const config = BRANDING;

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
