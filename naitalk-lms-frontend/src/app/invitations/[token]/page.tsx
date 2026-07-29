import { notFound } from 'next/navigation';
import { getTenantConfig } from '@/lib/tenant';
import { apiFetch, ApiError } from '@/lib/api-server';
import { AuthCard } from '@/components/auth-card';
import { AcceptInvitationForm } from '@/components/accept-invitation-form';

interface InvitationPreview {
  email: string;
  tenant_name: string;
  role_name: string;
}

export default async function AcceptInvitationPage({ params }: { params: Promise<{ token: string }> }) {
  const config = await getTenantConfig();
  if (!config) notFound();

  const { token } = await params;

  let invitation: InvitationPreview;
  let expired = false;
  try {
    const body = await apiFetch<{ data: InvitationPreview }>(`/api/v1/invitations/${token}`, { skipAuth: true });
    invitation = body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 410) {
      expired = true;
      invitation = { email: '', tenant_name: config.tenant.name, role_name: '' };
    } else if (error instanceof ApiError && error.status === 404) {
      notFound();
    } else {
      throw error;
    }
  }

  if (expired) {
    return (
      <AuthCard
        tenantName={config.tenant.name}
        logoUrl={config.branding?.logo_url ?? null}
        title="Invitation expired"
        subtitle="Ask whoever invited you to send a new one."
      >
        <p className="text-sm text-neutral-600">This invitation link is no longer valid.</p>
      </AuthCard>
    );
  }

  return (
    <AuthCard
      tenantName={config.tenant.name}
      logoUrl={config.branding?.logo_url ?? null}
      title={`Join ${invitation.tenant_name}`}
      subtitle={`You've been invited as ${invitation.role_name} (${invitation.email})`}
    >
      <AcceptInvitationForm token={token} />
    </AuthCard>
  );
}
