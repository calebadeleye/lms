export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
}

export function Pagination({ meta, onPageChange }: { meta: PaginationMeta; onPageChange: (page: number) => void }) {
  const lastPage = Math.max(1, Math.ceil(meta.total / meta.per_page));
  if (lastPage <= 1) return null;

  const from = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1;
  const to = Math.min(meta.page * meta.per_page, meta.total);

  return (
    <div className="flex items-center justify-between border-t border-neutral-200 px-4 py-3 text-sm">
      <span className="text-neutral-500">
        {from}–{to} of {meta.total}
      </span>
      <div className="flex items-center gap-2">
        <button
          onClick={() => onPageChange(meta.page - 1)}
          disabled={meta.page <= 1}
          className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 disabled:opacity-40"
        >
          Previous
        </button>
        <span className="text-xs text-neutral-500">
          Page {meta.page} of {lastPage}
        </span>
        <button
          onClick={() => onPageChange(meta.page + 1)}
          disabled={meta.page >= lastPage}
          className="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 disabled:opacity-40"
        >
          Next
        </button>
      </div>
    </div>
  );
}
