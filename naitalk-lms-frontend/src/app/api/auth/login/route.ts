import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getSession } from '@/lib/session';

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
  device_label: z.string().optional(),
});

/**
 * BFF login endpoint. The browser calls this, never Laravel directly. On
 * success we store the Sanctum token inside the encrypted HttpOnly session
 * cookie and return only non-sensitive user info — the raw token never
 * reaches client JS. See ARCHITECTURE.md §2.
 */
export async function POST(request: Request) {
  const parsed = loginSchema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }


  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
    },
    body: JSON.stringify(parsed.data),
    cache: 'no-store',
  });

  const body = await response.json();

  if (!response.ok) {
    return NextResponse.json(body, { status: response.status });
  }

  // MFA challenge — no session yet, hand the opaque challenge token back to
  // the client so it can call /api/auth/mfa/verify next.
  if (body.data?.mfa_required) {
    return NextResponse.json(body);
  }

  const session = await getSession();
  session.userId = body.data.user.id;
  session.publicId = body.data.user.public_id;
  session.name = body.data.user.name;
  session.email = body.data.user.email;
  session.token = body.data.token;
  session.expiresAt = body.data.expires_at;
  await session.save();

  return NextResponse.json({ data: { user: body.data.user } });
}

export async function GET() {
  return NextResponse.json({ errors: [{ code: 'method_not_allowed' }] }, { status: 405 });
}
