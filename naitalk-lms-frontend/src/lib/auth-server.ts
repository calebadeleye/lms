import 'server-only';
import { redirect } from 'next/navigation';
import { apiFetch, ApiError } from '@/lib/api-server';

export interface CurrentUser {
  user: { id: number; public_id: string; name: string; email: string; email_verified_at: string | null };
  tenant_id: string;
  role: { id: number; name: string; slug: string } | null;
  permissions: string[];
}

/** Redirects to /login when there is no valid session, or to /verify-email
 * when the session is valid but the account hasn't confirmed its email yet
 * — call at the top of any protected Server Component. The backend mirrors
 * this at the API layer (the `verified` middleware on tenant-scoped student
 * actions) as defense in depth, but gating here too means an unverified
 * visitor never even sees dashboard content, not just its data requests
 * failing underneath them. */
export async function requireUser(): Promise<CurrentUser> {
  try {
    const body = await apiFetch<{ data: CurrentUser }>('/api/v1/auth/me');

    if (!body.data.user.email_verified_at) {
      redirect('/verify-email');
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

export interface PlatformUser {
  user: { id: number; public_id: string; name: string; email: string };
  role: { id: number; name: string; slug: string };
  permissions: string[];
}

export async function requirePlatformStaff(): Promise<PlatformUser> {
  try {
    const body = await apiFetch<{ data: PlatformUser }>('/api/v1/platform/auth/me');
    return body.data;
  } catch (error) {
    if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
      redirect('/platform/login');
    }
    throw error;
  }
}
