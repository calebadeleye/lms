export default function NotFound() {
  return (
    <main className="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-24 text-center">
      <h1 className="text-2xl font-semibold text-neutral-900">Site not found</h1>
      <p className="max-w-md text-neutral-600">
        There&apos;s no academy configured for this address. If you followed a link to get here, double-check the
        URL.
      </p>
    </main>
  );
}
