import type { Metadata } from 'next';
import { BRANDING } from '@/lib/branding';
import { AuthCard } from '@/components/auth-card';
import { LoginForm } from '@/components/login-form';
import { safeRedirectPath } from '@/lib/safe-redirect';

export const metadata: Metadata = {
  title: 'Sign In',
  robots: { index: false, follow: true },
};

export default async function LoginPage({ searchParams }: { searchParams: Promise<{ redirect?: string }> }) {
  const config = BRANDING;
  const { redirect } = await searchParams;
  const returnTo = safeRedirectPath(redirect, '');
  const signupHref = returnTo ? `/signup?redirect=${encodeURIComponent(returnTo)}` : '/signup';

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title="Welcome back"
      subtitle={`Sign in to ${config.tenant.name}`}
    >
      <LoginForm redirectTo={safeRedirectPath(redirect)} signupHref={signupHref} />
    </AuthCard>
  );
}
