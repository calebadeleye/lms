import { NextResponse } from 'next/server';
import { getSession } from '@/lib/session';

export async function POST() {
  const session = await getSession();

  if (session.token) {
    // Best-effort: revoke the token server-side too. Even if this call
    // fails, destroying the local cookie below still logs the browser out.
    await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/logout`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${session.token}`,
        'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
      },
      cache: 'no-store',
    }).catch(() => null);
  }

  session.destroy();

  return NextResponse.json({ data: { success: true } });
}
