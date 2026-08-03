'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import { LogoutButton } from '@/components/logout-button';

const navItems = [
  { href: '/', label: 'Home' },
  { href: '/about', label: 'About' },
  { href: '/courses', label: 'Courses', expandable: true },
  { href: '/membership', label: 'Membership' },
  { href: '/coaching', label: 'Coaching' },
];

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
  const pathname = usePathname();

  return (
    <header className="sticky top-0 z-50 border-b border-[#e7ecea]/90 bg-white/95 backdrop-blur-xl">
      <div className="mx-auto flex h-[76px] max-w-[1240px] items-center justify-between gap-6 px-5 sm:px-7 lg:px-8">
        <Link href="/" className="flex shrink-0 items-center" aria-label={`${tenantName} home`}>
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={tenantName} className="h-[52px] w-auto max-w-[156px] object-contain" />
          ) : (
            <span className="text-base font-extrabold text-[#073d40]">{tenantName}</span>
          )}
        </Link>

        <nav aria-label="Primary navigation" className="hidden items-center gap-6 text-[14px] font-semibold text-[#172326] lg:flex xl:gap-7">
          {navItems.map((item) => {
            const isActive = item.href === '/' ? pathname === '/' : item.href.startsWith('/#') ? false : pathname.startsWith(item.href.split('?')[0]);
            return (
              <Link
                key={item.label}
                href={item.href}
                className={`inline-flex min-h-11 items-center gap-1 transition-colors hover:text-[#006c70] ${isActive ? 'text-[#006c70]' : ''}`}
              >
                {item.label}
                {item.expandable && (
                  <svg aria-hidden viewBox="0 0 16 16" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="1.7">
                    <path d="m5 6 3 3 3-3" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                )}
              </Link>
            );
          })}
        </nav>

        <div className="hidden shrink-0 items-center gap-3 lg:flex">
          {isAuthenticated ? (
            <>
              <Link href="/dashboard" className="inline-flex min-h-11 items-center rounded-lg border border-[#006c70] px-5 text-sm font-bold text-[#073d40] transition hover:bg-[#006c70]/5">
                Dashboard
              </Link>
              <LogoutButton className="min-h-11 rounded-lg bg-[#f4b728] px-5 text-sm font-bold text-[#172326] transition hover:bg-[#e9aa18]" />
            </>
          ) : (
            <>
              <Link href="/login" className="inline-flex min-h-11 items-center rounded-lg border border-[#006c70] px-6 text-sm font-bold text-[#073d40] transition hover:bg-[#006c70]/5">
                Log In
              </Link>
              <Link href="/register" className="inline-flex min-h-11 items-center rounded-lg bg-[#f4b728] px-6 text-sm font-extrabold text-[#172326] transition hover:-translate-y-0.5 hover:bg-[#e9aa18]">
                Join HR GEMs
              </Link>
            </>
          )}
        </div>

        <button
          type="button"
          className="grid h-11 w-11 place-items-center rounded-lg border border-[#d7e1de] text-[#073d40] lg:hidden"
          onClick={() => setOpen((value) => !value)}
          aria-expanded={open}
          aria-controls="mobile-navigation"
          aria-label={open ? 'Close navigation menu' : 'Open navigation menu'}
        >
          <span className="relative h-5 w-5" aria-hidden>
            <span className={`absolute left-0 top-1 block h-0.5 w-5 bg-current transition ${open ? 'translate-y-1.5 rotate-45' : ''}`} />
            <span className={`absolute left-0 top-2.5 block h-0.5 w-5 bg-current transition ${open ? 'opacity-0' : ''}`} />
            <span className={`absolute left-0 top-4 block h-0.5 w-5 bg-current transition ${open ? '-translate-y-1.5 -rotate-45' : ''}`} />
          </span>
        </button>
      </div>

      {open && (
        <nav id="mobile-navigation" aria-label="Mobile navigation" className="border-t border-[#e7ecea] bg-white px-5 py-5 shadow-[0_18px_30px_rgba(7,61,64,0.08)] lg:hidden">
          <ul className="mx-auto max-w-[1240px] space-y-1">
            {navItems.map((item) => (
              <li key={item.label}>
                <Link href={item.href} onClick={() => setOpen(false)} className="flex min-h-12 items-center justify-between rounded-lg px-3 text-base font-semibold text-[#172326] hover:bg-[#f7f6f0] hover:text-[#006c70]">
                  {item.label}
                  <span aria-hidden className="text-[#006c70]">→</span>
                </Link>
              </li>
            ))}
            <li className="grid grid-cols-2 gap-3 border-t border-[#e7ecea] pt-4">
              {isAuthenticated ? (
                <>
                  <Link href="/dashboard" onClick={() => setOpen(false)} className="grid min-h-11 place-items-center rounded-lg border border-[#006c70] text-sm font-bold text-[#073d40]">Dashboard</Link>
                  <LogoutButton className="min-h-11 rounded-lg bg-[#f4b728] text-sm font-bold text-[#172326]" />
                </>
              ) : (
                <>
                  <Link href="/login" onClick={() => setOpen(false)} className="grid min-h-11 place-items-center rounded-lg border border-[#006c70] text-sm font-bold text-[#073d40]">Log In</Link>
                  <Link href="/register" onClick={() => setOpen(false)} className="grid min-h-11 place-items-center rounded-lg bg-[#f4b728] text-sm font-bold text-[#172326]">Join HR GEMs</Link>
                </>
              )}
            </li>
          </ul>
        </nav>
      )}
    </header>
  );
}
