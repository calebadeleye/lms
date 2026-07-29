import 'server-only';
import { cookies } from 'next/headers';
import { getIronSession, type IronSession, type SessionOptions } from 'iron-session';

export interface SessionData {
  userId?: number;
  publicId?: string;
  name?: string;
  email?: string;
  tenantId?: string;
  /** Set only by platform-login/route.ts. tenantId is NOT a reliable way to
   * tell tenant and platform-staff sessions apart — neither login nor
   * register (tenant-side) ever sets it — so anything that needs to branch
   * on session type (e.g. logout/route.ts picking which backend logout
   * endpoint to call) must check this flag instead. */
  isPlatformStaff?: boolean;
  /** Sanctum bearer token — never sent to the browser, only ever read
   * server-side to attach `Authorization: Bearer` on outgoing API calls. */
  token?: string;
  expiresAt?: string;
  /** Set while a platform support agent is impersonating this session. */
  impersonation?: {
    staffId: number;
    reason: string;
    expiresAt: string;
  };
}

const sessionOptions: SessionOptions = {
  password: process.env.SESSION_SECRET as string,
  cookieName: 'naitalk_session',
  cookieOptions: {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
  },
};

if (!process.env.SESSION_SECRET || process.env.SESSION_SECRET.length < 32) {
  // Fails fast in any environment rather than silently encrypting with a
  // weak/missing key.
  throw new Error('SESSION_SECRET must be set to a random string of at least 32 characters.');
}

export async function getSession(): Promise<IronSession<SessionData>> {
  const cookieStore = await cookies();
  return getIronSession<SessionData>(cookieStore, sessionOptions);
}

export async function requireSession(): Promise<IronSession<SessionData> & { token: string }> {
  const session = await getSession();

  if (!session.token) {
    throw new Error('UNAUTHENTICATED');
  }

  return session as IronSession<SessionData> & { token: string };
}
