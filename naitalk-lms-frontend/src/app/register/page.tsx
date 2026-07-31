import type { Metadata } from 'next';
import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { RegisterForm } from '@/components/register-form';

export const metadata: Metadata = {
  title: 'Join HR GEMs',
  description:
    'Create your HR GEMs Coach Network account and join a community of HR professionals and ' +
    'coaches learning transformational skills to grow their careers and impact.',
};

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
