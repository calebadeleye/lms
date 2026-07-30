import { NextResponse, type NextRequest } from 'next/server';
import { getSession } from '@/lib/session';

/**
 * Generic authenticated proxy for Client Components, which can't read the
 * HttpOnly session cookie themselves and so have no way to attach the
 * Sanctum bearer token to a direct fetch. Server Components should call
 * `apiFetch()` from lib/api-server.ts directly instead — this proxy adds an
 * extra hop that's only necessary for browser-side JS.
 */
async function forward(request: NextRequest, segments: string[]) {
  const session = await getSession();

  const targetUrl = new URL(`/api/v1/${segments.join('/')}`, process.env.BACKEND_SERVER_URL);
  targetUrl.search = request.nextUrl.search;

  const headers = new Headers();
  headers.set('Accept', 'application/json');
  headers.set('X-Internal-Secret', process.env.BACKEND_INTERNAL_SECRET as string);

  const incomingContentType = request.headers.get('content-type');
  if (incomingContentType) headers.set('Content-Type', incomingContentType);

  const idempotencyKey = request.headers.get('idempotency-key');
  if (idempotencyKey) headers.set('Idempotency-Key', idempotencyKey);

  if (session.token) {
    headers.set('Authorization', `Bearer ${session.token}`);
  }

  const hasBody = !['GET', 'HEAD'].includes(request.method);

  const response = await fetch(targetUrl, {
    method: request.method,
    headers,
    body: hasBody ? await request.arrayBuffer() : undefined,
    cache: 'no-store',
  });

  const responseContentType = response.headers.get('content-type') ?? 'application/json';
  const body = await response.arrayBuffer();

  return new NextResponse(body, {
    status: response.status,
    headers: { 'Content-Type': responseContentType },
  });
}

type RouteParams = { params: Promise<{ path: string[] }> };

export async function GET(request: NextRequest, { params }: RouteParams) {
  return forward(request, (await params).path);
}

export async function POST(request: NextRequest, { params }: RouteParams) {
  return forward(request, (await params).path);
}

export async function PUT(request: NextRequest, { params }: RouteParams) {
  return forward(request, (await params).path);
}

export async function PATCH(request: NextRequest, { params }: RouteParams) {
  return forward(request, (await params).path);
}

export async function DELETE(request: NextRequest, { params }: RouteParams) {
  return forward(request, (await params).path);
}
