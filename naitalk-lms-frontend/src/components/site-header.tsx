'use client';

import Link from 'next/link';
import { useState } from 'react';
import { LogoutButton } from '@/components/logout-button';

const navItems = [
  { href: '/', label: 'Home' },
  { href: '/courses', label: 'Courses' },
  { href: '/membership', label: 'Membership' },
  { href: '/coaching', label: 'Coaching' },
];

/**
 * This header is shared by every public-facing page (homepage, courses,
 * membership, coaching, coming-soon) — including ones an already-logged-in
 * visitor can reach from inside their own dashboard nav. `isAuthenticated`
 * must be passed by every caller (each is a Server Component that can call
 * `getOptionalUser()`/`requireUser()`) so a logged-in visitor sees
 * "Dashboard"/"Logout" here instead of "Login"/"Sign Up" — otherwise the
 * page looks like it doesn't recognize them even though their session is
 * perfectly valid underneath.
 */
export function SiteHeader({
  tenantName,
  logoUrl = null,
  isAuthenticated = false,
}: {
  tenantName: string;
  logoUrl?: string | null;
  isAuthenticated?: boolean;
}) {
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
        <Link href="/" className="flex items-center gap-2">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={tenantName} className="h-9 max-w-[9rem] object-contain" />
          ) : (
            <>
              <span
                aria-hidden
                className="grid h-9 w-9 place-items-center rounded-md bg-[var(--brand-primary)] text-[var(--brand-accent)] font-bold"
              >
                {tenantName.charAt(0)}
              </span>
              <span className="text-sm font-bold leading-tight text-[var(--brand-primary)]">
                {tenantName}
                <span className="block text-[10px] font-medium tracking-wide text-neutral-500">ONLINE ACADEMY</span>
              </span>
            </>
          )}
        </Link>

        <nav className="hidden items-center gap-6 text-sm font-medium text-neutral-700 md:flex">
          {navItems.map((item) => (
            <Link key={item.label} href={item.href} className="hover:text-[var(--brand-primary)]">
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-3 md:flex">
          {isAuthenticated ? (
            <>
              <Link href="/dashboard" className="text-sm font-medium text-neutral-700 hover:text-[var(--brand-primary)]">
                Dashboard
              </Link>
              <LogoutButton className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50" />
            </>
          ) : (
            <>
              <Link href="/login" className="text-sm font-medium text-neutral-700 hover:text-[var(--brand-primary)]">
                Login
              </Link>
              <Link
                href="/register"
                className="rounded-md bg-[var(--brand-accent)] px-4 py-2 text-sm font-semibold text-neutral-900 shadow-sm hover:opacity-90"
              >
                Sign Up
              </Link>
            </>
          )}
        </div>

        <button
          type="button"
          className="grid h-9 w-9 place-items-center rounded-md border border-neutral-300 md:hidden"
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          aria-label="Toggle navigation menu"
        >
          <span aria-hidden>{open ? '✕' : '☰'}</span>
        </button>
      </div>

      {open && (
        <nav className="border-t border-neutral-200 bg-white px-4 py-3 md:hidden">
          <ul className="flex flex-col gap-3 text-sm font-medium text-neutral-700">
            {navItems.map((item) => (
              <li key={item.label}>
                <Link href={item.href} onClick={() => setOpen(false)}>
                  {item.label}
                </Link>
              </li>
            ))}
            <li className="mt-2 flex gap-3 border-t border-neutral-200 pt-3">
              {isAuthenticated ? (
                <>
                  <Link
                    href="/dashboard"
                    onClick={() => setOpen(false)}
                    className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-center"
                  >
                    Dashboard
                  </Link>
                  <LogoutButton className="flex-1 rounded-md bg-[var(--brand-accent)] px-3 py-2 text-center font-semibold text-neutral-900" />
                </>
              ) : (
                <>
                  <Link href="/login" className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-center">
                    Login
                  </Link>
                  <Link
                    href="/register"
                    className="flex-1 rounded-md bg-[var(--brand-accent)] px-3 py-2 text-center font-semibold text-neutral-900"
                  >
                    Sign Up
                  </Link>
                </>
              )}
            </li>
          </ul>
        </nav>
      )}
    </header>
  );
}
