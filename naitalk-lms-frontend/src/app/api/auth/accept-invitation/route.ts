import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getCurrentHostname } from '@/lib/tenant';
import { getSession } from '@/lib/session';

const acceptSchema = z
  .object({
    token: z.string().min(1),
    name: z.string().min(1).max(255),
    password: z.string().min(10),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

export async function POST(request: Request) {
  const parsed = acceptSchema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const { token, ...body } = parsed.data;
  const hostname = await getCurrentHostname();

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/invitations/${token}/accept`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Tenant-Hostname': hostname,
      'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
    },
    body: JSON.stringify(body),
    cache: 'no-store',
  });

  const responseBody = await response.json();

  if (!response.ok) {
    return NextResponse.json(responseBody, { status: response.status });
  }

  const session = await getSession();
  session.userId = responseBody.data.user.id;
  session.publicId = responseBody.data.user.public_id;
  session.name = responseBody.data.user.name;
  session.email = responseBody.data.user.email;
  session.token = responseBody.data.token;
  session.expiresAt = responseBody.data.expires_at;
  await session.save();

  return NextResponse.json({ data: { user: responseBody.data.user } }, { status: 201 });
}
