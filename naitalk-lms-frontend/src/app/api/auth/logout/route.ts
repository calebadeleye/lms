import { NextResponse } from 'next/server';
import { getCurrentHostname } from '@/lib/tenant';
import { getSession } from '@/lib/session';

export async function POST() {
  const session = await getSession();

  if (session.token) {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      Authorization: `Bearer ${session.token}`,
      'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
    };

    // A platform-staff session (see platform-login/route.ts) must hit the
    // platform logout route — it never resolves a TenantContext, so it
    // takes no X-Tenant-Hostname either. Every other session (login,
    // register, invitation accept) is tenant-side.
    const path = session.isPlatformStaff ? '/api/v1/platform/auth/logout' : '/api/v1/auth/logout';
    if (!session.isPlatformStaff) headers['X-Tenant-Hostname'] = await getCurrentHostname();

    // Best-effort: revoke the token server-side too. Even if this call
    // fails, destroying the local cookie below still logs the browser out.
    await fetch(`${process.env.BACKEND_SERVER_URL}${path}`, {
      method: 'POST',
      headers,
      cache: 'no-store',
    }).catch(() => null);
  }

  session.destroy();

  return NextResponse.json({ data: { success: true } });
}
