import Link from 'next/link';
import { LogoutButton } from '@/components/logout-button';
import { getTenantConfig } from '@/lib/tenant';

export interface NavItem {
  href: string;
  label: string;
}

export async function DashboardShell({
  tenantName,
  navItems,
  userName,
  activeHref,
  children,
  impersonationBanner,
}: {
  tenantName: string;
  navItems: NavItem[];
  userName: string;
  activeHref: string;
  children: React.ReactNode;
  impersonationBanner?: React.ReactNode;
}) {
  const config = await getTenantConfig();
  const logoUrl = config?.branding?.logo_url ?? null;

  return (
    <div className="flex min-h-full">
      <aside className="hidden w-64 shrink-0 flex-col bg-[var(--tenant-primary)] text-white md:flex">
        <div className="flex items-center px-5 py-5">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={tenantName} className="h-14 max-w-full object-contain" />
          ) : (
            <div className="flex items-center gap-2">
              <span
                aria-hidden
                className="grid h-8 w-8 place-items-center rounded-md bg-white/10 text-sm font-bold text-[var(--tenant-accent)]"
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
        {impersonationBanner}
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
