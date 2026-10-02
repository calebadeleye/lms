import { notFound } from 'next/navigation';
import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { ResetPasswordForm } from '@/components/reset-password-form';

export default async function ResetPasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ token?: string; email?: string }>;
}) {
  const config = BRANDING;

  const { token, email } = await searchParams;
  if (!token || !email) notFound();

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Choose a new password"
      subtitle={email}
    >
      <ResetPasswordForm token={token} email={email} />
    </AuthCard>
  );
}
