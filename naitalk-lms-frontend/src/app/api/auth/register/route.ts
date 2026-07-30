import { NextResponse } from 'next/server';
import { z } from 'zod';
import { getSession } from '@/lib/session';

const ACK_KEYS = [
  'ack_impact_beyond_earning',
  'ack_growth_mindset',
  'ack_interest_in_coaching',
  'ack_positive_impact',
] as const;

const registerSchema = z
  .object({
    name: z.string().min(1).max(255),
    email: z.string().email(),
    password: z.string().min(10),
    password_confirmation: z.string(),
    ack_impact_beyond_earning: z.literal('1'),
    ack_growth_mindset: z.literal('1'),
    ack_interest_in_coaching: z.literal('1'),
    ack_positive_impact: z.literal('1'),
    motivation: z.string().max(1000).optional(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

export async function POST(request: Request) {
  const incoming = await request.formData();
  const photo = incoming.get('photo');

  const fields: Record<string, string> = {};
  for (const [key, value] of incoming.entries()) {
    if (key !== 'photo' && typeof value === 'string') fields[key] = value;
  }

  const parsed = registerSchema.safeParse(fields);

  if (!parsed.success) {
    return NextResponse.json({ errors: parsed.error.flatten().fieldErrors }, { status: 422 });
  }

  const outgoing = new FormData();
  outgoing.set('name', parsed.data.name);
  outgoing.set('email', parsed.data.email);
  outgoing.set('password', parsed.data.password);
  outgoing.set('password_confirmation', parsed.data.password_confirmation);
  for (const key of ACK_KEYS) outgoing.set(key, '1');
  if (parsed.data.motivation) outgoing.set('motivation', parsed.data.motivation);
  if (photo instanceof File && photo.size > 0) outgoing.set('photo', photo);

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}/api/v1/auth/register`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
    },
    body: outgoing,
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
