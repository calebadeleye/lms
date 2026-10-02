import type { Metadata } from 'next';
import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { SignupForm } from '@/components/signup-form';
import { safeRedirectPath } from '@/lib/safe-redirect';

export const metadata: Metadata = {
  title: 'Create a Learner Account',
  description: 'Create a free account to buy and take courses on HR GEMs.',
  robots: { index: false, follow: true },
};

export default async function SignupPage({ searchParams }: { searchParams: Promise<{ redirect?: string }> }) {
  const config = BRANDING;
  const { redirect } = await searchParams;

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Create your account"
      subtitle={`Sign up to learn with ${config.tenant.name}`}
    >
      <SignupForm redirectTo={safeRedirectPath(redirect, '')} />
    </AuthCard>
  );
}
