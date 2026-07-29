import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getCurrentHostname } from '@/lib/tenant';
import { getSession } from '@/lib/session';

const registerSchema = z
  .object({
    name: z.string().min(1).max(255),
    email: z.string().email(),
    password: z.string().min(10),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

export async function POST(request: Request) {
  const parsed = registerSchema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const hostname = await getCurrentHostname();

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Tenant-Hostname': hostname,
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
  await session.save();

  return NextResponse.json({ data: { user: body.data.user } }, { status: 201 });
}
