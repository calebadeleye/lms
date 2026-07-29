export function SiteFooter({ tenantName }: { tenantName: string }) {
  return (
    <footer className="border-t border-neutral-200 bg-white">
      <div className="mx-auto max-w-6xl px-4 py-8 text-sm text-neutral-500 sm:px-6">
        <p>
          &copy; {new Date().getFullYear()} {tenantName}. All rights reserved.
        </p>
        <p className="mt-1">Made by NAI TALK</p>
      </div>
    </footer>
  );
}
