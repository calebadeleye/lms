import Link from 'next/link';
import { LogoutButton } from '@/components/logout-button';

export interface NavItem {
  href: string;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
}

/**
 * Deliberately does not use the tenant `--tenant-*` CSS variables — this is
 * NAI TALK's own internal tool, never white-labelled, and must look the
 * same regardless of which tenant's domain the platform admin happens to
 * be reached through in local development. It does use NAI TALK's own
 * fixed brand color (--naitalk-green*, set in globals.css) and a permanent
 * dark theme (.platform-bg, also globals.css) — this is the one place in
 * the app that isn't light-themed.
 */
export function PlatformShell({
  navItems,
  userName,
  userEmail,
  activeHref,
  children,
}: {
  navItems: NavItem[];
  userName: string;
  userEmail?: string;
  activeHref: string;
  children: React.ReactNode;
}) {
  const initials = userName
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    // min-h-screen, not min-h-full: body uses flex-col but never gives this
    // subtree an explicit height, so a percentage-based min-height collapses
    // to the content's own height on any page shorter than the viewport —
    // the gap below then shows body's white --background through the dark
    // theme. min-h-screen is viewport-relative and doesn't have that problem.
    <div className="platform-bg flex min-h-screen text-white">
      <aside className="hidden w-64 shrink-0 flex-col border-r border-white/10 bg-white/[0.03] backdrop-blur-xl md:flex">
        <div className="px-5 py-5">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/naitalk-logo.png" alt="NAI TALK" className="h-7 w-auto" />
          <span className="mt-1 block text-[10px] font-medium tracking-wide text-white/40">PLATFORM CONTROL</span>
        </div>
        <nav className="flex-1 space-y-1 px-3">
          {navItems.map((item) => {
            const active = item.href === activeHref;
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex items-center gap-2.5 rounded-full px-3.5 py-2 text-sm font-medium transition-colors ${
                  active
                    ? 'bg-[var(--naitalk-green)]/20 text-white ring-1 ring-inset ring-[var(--naitalk-green)]/40'
                    : 'text-white/60 hover:bg-white/5 hover:text-white'
                }`}
              >
                {Icon && <Icon className={`h-4 w-4 shrink-0 ${active ? 'text-[var(--naitalk-green)]' : ''}`} />}
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="space-y-2 px-3 pb-5">
          <LogoutButton
            redirectTo="/platform/login"
            className="w-full rounded-full px-3.5 py-2 text-left text-sm font-medium text-white/60 hover:bg-white/5 hover:text-white"
          />
          <div className="flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5">
            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--naitalk-green)] text-xs font-bold text-white">
              {initials}
            </span>
            <div className="min-w-0">
              <p className="truncate text-xs font-semibold text-white">{userName}</p>
              {userEmail && <p className="truncate text-[11px] text-white/50">{userEmail}</p>}
            </div>
          </div>
        </div>
      </aside>

      <div className="flex min-h-full flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-white/10 bg-white/[0.02] px-4 py-3 backdrop-blur-xl sm:px-6">
          <p className="text-sm font-medium text-white/60 md:hidden">NAI TALK Platform</p>
          <div className="ml-auto flex items-center gap-2.5">
            <span className="hidden text-sm text-white/50 sm:inline">Welcome,</span>
            <span className="text-sm font-semibold text-white">{userName}</span>
            <span className="grid h-8 w-8 place-items-center rounded-full bg-[var(--naitalk-green)] text-xs font-bold text-white">
              {initials}
            </span>
          </div>
        </header>
        <main className="flex-1 p-4 sm:p-6">{children}</main>
      </div>
    </div>
  );
}
