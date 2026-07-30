import { NextResponse } from 'next/server';
import { z } from 'zod';

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
    // handleSubmit() always sends all four as '1' or '0' (never omits a
    // key) — only one needs to actually be '1', enforced below, since Zod
    // doesn't have a built-in "at least one of these keys" check.
    ack_impact_beyond_earning: z.enum(['0', '1']).optional(),
    ack_growth_mindset: z.enum(['0', '1']).optional(),
    ack_interest_in_coaching: z.enum(['0', '1']).optional(),
    ack_positive_impact: z.enum(['0', '1']).optional(),
    motivation: z.string().max(1000).optional(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })
  .refine((data) => ACK_KEYS.some((key) => data[key] === '1'), {
    message: 'Please confirm at least one of the membership requirements.',
    path: ['ack_impact_beyond_earning'],
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
  for (const key of ACK_KEYS) outgoing.set(key, parsed.data[key] === '1' ? '1' : '0');
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

  // Deliberately no session here — an applicant isn't logged in until the
  // registration fee is paid. The payment_token lets the browser start (and
  // later, via /checkout/callback, check on) that one payment without a
  // session; they log in normally afterward with the password they just set.
  return NextResponse.json({ data: { user: body.data.user, payment_token: body.data.payment_token } }, { status: 201 });
}
