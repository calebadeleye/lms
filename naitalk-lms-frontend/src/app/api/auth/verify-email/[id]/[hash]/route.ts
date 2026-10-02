import { NextResponse, type NextRequest } from 'next/server';

/**
 * Target of the link in the verification email. The link is signed by the
 * backend for its own `verification.verify` route (path + query), so this
 * just relays it — same path segments, same query string, byte for byte —
 * and then sends the browser on to the /verify-email page with the outcome.
 *
 * The redirect is a relative Location on purpose: behind the reverse proxy
 * `request.url` can carry the internal host, and an absolute redirect built
 * from it would send the user to localhost.
 */
export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ id: string; hash: string }> }
) {
  const { id, hash } = await params;

  const target = new URL(
    `/api/v1/auth/verify-email/${encodeURIComponent(id)}/${encodeURIComponent(hash)}`,
    process.env.BACKEND_SERVER_URL
  );
  target.search = request.nextUrl.search;

  let status: 'verified' | 'invalid' = 'invalid';

  try {
    const response = await fetch(target, {
      headers: {
        Accept: 'application/json',
        'X-Internal-Secret': process.env.BACKEND_INTERNAL_SECRET as string,
      },
      // The backend answers with a redirect into the frontend; we only want
      // to read where it points, not follow it.
      redirect: 'manual',
      cache: 'no-store',
    });

    const location = response.headers.get('location') ?? '';
    if (response.status >= 300 && response.status < 400 && location.includes('status=verified')) {
      status = 'verified';
    }
  } catch {
    // Backend unreachable — fall through to the "invalid or expired" page,
    // which offers a resend, rather than a raw error page.
  }

  return new NextResponse(null, { status: 302, headers: { Location: `/verify-email?status=${status}` } });
}
