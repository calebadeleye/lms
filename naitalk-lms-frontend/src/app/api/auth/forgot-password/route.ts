import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getCurrentHostname } from '@/lib/tenant';

const schema = z.object({ email: z.string().email() });

export async function POST(request: Request) {
  const parsed = schema.safeParse(await request.json().catch(() => null));

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const hostname = await getCurrentHostname();

  await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/forgot-password`, {
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

  // Always a generic success response, regardless of what the backend
  // says — never reveal whether an email exists.
  return NextResponse.json({ data: { success: true } });
}
