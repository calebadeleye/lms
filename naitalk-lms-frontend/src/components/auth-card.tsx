export function AuthCard({
  tenantName,
  logoUrl = null,
  title,
  subtitle,
  maxWidthClassName = 'max-w-md',
  children,
}: {
  tenantName: string;
  logoUrl?: string | null;
  title: string;
  subtitle: string;
  maxWidthClassName?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-full flex-1 items-center justify-center bg-neutral-50 px-4 py-12">
      <div className={`w-full ${maxWidthClassName} rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm sm:p-10`}>
        <div className="mb-6 text-center">
          {logoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logoUrl} alt={tenantName} className="mx-auto mb-3 h-12 max-w-[10rem] object-contain" />
          ) : (
            <span
              aria-hidden
              className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-md bg-[var(--brand-primary)] text-lg font-bold text-[var(--brand-accent)]"
            >
              {tenantName.charAt(0)}
            </span>
          )}
          <h1 className="text-xl font-bold text-neutral-900">{title}</h1>
          <p className="mt-1 text-sm text-neutral-500">{subtitle}</p>
        </div>
        {children}
      </div>
    </div>
  );
}
