import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getCurrentHostname } from '@/lib/tenant';

const schema = z
  .object({
    token: z.string().min(1),
    email: z.string().email(),
    password: z.string().min(10),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

export async function POST(request: Request) {
  const parsed = schema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const hostname = await getCurrentHostname();

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/reset-password`, {
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

  return NextResponse.json(body, { status: response.status });
}
