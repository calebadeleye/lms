import 'server-only';
import { getSession } from '@/lib/session';

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`API request failed with status ${status}`);
  }
}

/**
 * Direct server-to-server call to the Laravel API, for use from Server
 * Components and Route Handlers. Per Next.js guidance, Server Components
 * should fetch data directly rather than round-tripping through this app's
 * own Route Handlers — that extra hop is what the `/api/v1/[...path]` proxy
 * exists for (Client Components, which can't read the HttpOnly session
 * cookie themselves).
 */
export async function apiFetch<T = unknown>(
  path: string,
  init: RequestInit & { skipAuth?: boolean } = {},
): Promise<T> {
  const session = init.skipAuth ? null : await getSession();

  const headers = new Headers(init.headers);
  headers.set('X-Internal-Secret', process.env.BACKEND_INTERNAL_SECRET as string);
  headers.set('Accept', 'application/json');

  if (!headers.has('Content-Type') && init.body) {
    headers.set('Content-Type', 'application/json');
  }

  if (session?.token) {
    headers.set('Authorization', `Bearer ${session.token}`);
  }

  const response = await fetch(`${process.env.BACKEND_SERVER_URL}${path}`, {
    ...init,
    headers,
    cache: init.cache ?? 'no-store',
  });

  const contentType = response.headers.get('content-type') ?? '';
  const body = contentType.includes('application/json') ? await response.json() : await response.text();

  if (!response.ok) {
    throw new ApiError(response.status, body);
  }

  return body as T;
}
