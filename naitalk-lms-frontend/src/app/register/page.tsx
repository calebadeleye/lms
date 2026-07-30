import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { RegisterForm } from '@/components/register-form';

export default async function RegisterPage() {
  const config = BRANDING;

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Create your account"
      subtitle={`Join ${config.tenant.name}`}
      maxWidthClassName="max-w-4xl"
    >
      <RegisterForm />
    </AuthCard>
  );
}
