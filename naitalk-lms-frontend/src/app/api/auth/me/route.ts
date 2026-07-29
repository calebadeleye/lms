import { NextResponse } from 'next/server';
import { apiFetch, ApiError } from '@/lib/api-server';
import { getSession } from '@/lib/session';

/**
 * Client Components (e.g. the nav bar) call this to get the current user +
 * tenant role/permissions. Proxies to Laravel rather than trusting the
 * session cookie's cached name/email, since roles/permissions can change
 * server-side between requests.
 */
export async function GET() {
  const session = await getSession();

  if (!session.token) {
    return NextResponse.json({ data: null }, { status: 200 });
  }

  try {
    const body = await apiFetch('/api/v1/auth/me');
    return NextResponse.json(body);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      session.destroy();
      return NextResponse.json({ data: null }, { status: 200 });
    }

    throw error;
  }
}
