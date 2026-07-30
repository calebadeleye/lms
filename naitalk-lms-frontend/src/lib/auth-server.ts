import 'server-only';
import { redirect } from 'next/navigation';
import { apiFetch, ApiError } from '@/lib/api-server';

export interface CurrentUser {
  user: { id: number; public_id: string; name: string; email: string; email_verified_at: string | null };
  membership_status: 'pending' | 'active' | 'inactive' | 'rejected';
  role: { id: number; name: string; slug: string } | null;
  permissions: string[];
}

/** Redirects to /login when there is no valid session, to /verify-email when
 * the session is valid but the account hasn't confirmed its email yet, or to
 * /onboarding/pending when the membership application hasn't been approved —
 * call at the top of any protected Server Component. The backend mirrors
 * this at the API layer (`verified`/`approved` middleware on member action
 * routes) as defense in depth, but gating here too means a visitor never
 * even sees dashboard content, not just its data requests failing
 * underneath them. */
export async function requireUser(): Promise<CurrentUser> {
  try {
    const body = await apiFetch<{ data: CurrentUser }>('/api/v1/auth/me');

    if (!body.data.user.email_verified_at) {
      redirect('/verify-email');
    }

    if (body.data.membership_status !== 'active') {
      redirect('/onboarding/pending');
    }

    return body.data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      redirect('/login');
    }
    throw error;
  }
}

/** Non-redirecting check for public pages that render differently for
 * logged-in vs anonymous visitors (course detail's enrol button, etc.). */
export async function getOptionalUser(): Promise<CurrentUser | null> {
  try {
    const body = await apiFetch<{ data: CurrentUser }>('/api/v1/auth/me');
    return body.data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      return null;
    }
    throw error;
  }
}
