/** Only same-site relative paths are honoured as post-auth destinations —
 * anything else (absolute URLs, protocol-relative `//host`, backslash
 * tricks) would turn the `?redirect=` param into an open redirect. */
export function safeRedirectPath(value: string | null | undefined, fallback = '/dashboard'): string {
  if (!value || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return fallback;
  return value;
}
