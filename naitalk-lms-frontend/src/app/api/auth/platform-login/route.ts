import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getSession } from '@/lib/session';

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

/**
 * Separate from /api/auth/login on purpose: platform staff authenticate
 * against Laravel's /api/v1/platform/auth/login, which never resolves a
 * TenantContext. No X-Tenant-Hostname is needed or sent.
 */
export async function POST(request: Request) {
  const parsed = loginSchema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/platform/auth/login`, {
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

  const session = await getSession();
  session.userId = body.data.user.id;
  session.publicId = body.data.user.public_id;
  session.name = body.data.user.name;
  session.email = body.data.user.email;
  session.token = body.data.token;
  session.expiresAt = body.data.expires_at;
  // No tenantId — this session is a platform-staff session, structurally
  // distinct from a tenant membership session. isPlatformStaff is the
  // actual discriminator other routes (logout) branch on.
  session.isPlatformStaff = true;
  await session.save();

  return NextResponse.json({ data: { user: body.data.user } });
}
