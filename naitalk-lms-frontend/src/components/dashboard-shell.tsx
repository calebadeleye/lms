import Link from 'next/link';
import { LogoutButton } from '@/components/logout-button';
import { BRANDING } from '@/lib/branding';

export interface NavItem {
  href: string;
  label: string;
}

export function DashboardShell({
  tenantName,
  navItems,
  userName,
  activeHref,
  children,
}: {
  tenantName: string;
  navItems: NavItem[];
  userName: string;
  activeHref: string;
  children: React.ReactNode;
}) {
  const logoUrl = BRANDING.branding.logo_url;

  return (
    <div className="flex min-h-screen">
      <aside className="hidden w-64 shrink-0 flex-col bg-[var(--brand-primary)] text-white md:flex">
        <div className="flex items-center px-5 py-5">
          {logoUrl ? (
            // The logo's own linework is teal — on this teal sidebar it
            // would vanish, so it needs a light backing chip to read at all.
            <div className="rounded-md bg-white/95 px-3 py-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={logoUrl} alt={tenantName} className="h-10 max-w-full object-contain" />
            </div>
          ) : (
            <div className="flex items-center gap-2">
              <span
                aria-hidden
                className="grid h-8 w-8 place-items-center rounded-md bg-white/10 text-sm font-bold text-[var(--brand-accent)]"
              >
                {tenantName.charAt(0)}
              </span>
              <span className="text-sm font-bold leading-tight">{tenantName}</span>
            </div>
          )}
        </div>
        <nav className="flex-1 space-y-1 px-3">
          {navItems.map((item) => {
            const active = item.href === activeHref;
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`block rounded-md px-3 py-2 text-sm font-medium ${
                  active ? 'bg-white/15 text-white' : 'text-white/75 hover:bg-white/10 hover:text-white'
                }`}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>
        <div className="px-3 pb-5">
          <LogoutButton className="w-full rounded-md px-3 py-2 text-left text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white" />
        </div>
      </aside>

      <div className="flex min-h-full flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-neutral-200 bg-white px-4 py-3 sm:px-6">
          <p className="text-sm font-medium text-neutral-500 md:hidden">{tenantName}</p>
          <div className="ml-auto flex items-center gap-2">
            <span className="hidden text-sm text-neutral-500 sm:inline">Welcome,</span>
            <span className="text-sm font-semibold text-neutral-900">{userName}</span>
          </div>
        </header>
        <main className="flex-1 bg-neutral-50 p-4 sm:p-6">{children}</main>
      </div>
    </div>
  );
}
