import { Head, router } from '@inertiajs/react';
import { Fragment, FormEvent, useState } from 'react';

type Operation = {
  id: number;
  reference: string;
  status: string;
  total_items: number;
  processed_items: number;
  successful_items: number;
  failed_items: number;
  idempotency_key?: string | null;
  created_at?: string | null;
  metadata?: { pending_items?: number | null } | null;
  user?: { name?: string | null; email?: string | null } | null;
};

type Link = { url: string | null; label: string; active: boolean };

export default function BulkOperations({
  operations,
}: {
  operations: { data: Operation[]; links?: Link[] };
}) {
  const [openId, setOpenId] = useState<number | null>(null);
  const [status, setStatus] = useState('');
  const [reference, setReference] = useState('');

  const submitFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const params: Record<string, string> = {};
    if (status) params.status = status;
    if (reference.trim()) params.reference = reference.trim();
    router.get('/admin/vtu/bulk', params, { preserveState: true, replace: true });
  };

  const resetFilters = () => {
    setStatus('');
    setReference('');
    router.get('/admin/vtu/bulk', {}, { preserveState: true, replace: true });
  };

  return (
    <>
      <Head title="VTU Bulk Operations" />
      <div className="p-4 md:p-6">
        <h1 className="text-2xl font-bold">VTU Bulk Operations</h1>
        <p className="mt-1 text-sm text-slate-500">
          Monitor bulk airtime, data and SMS operations without exposing provider secrets.
        </p>

        <form
          onSubmit={submitFilters}
          className="mt-5 flex flex-col gap-2 rounded-xl border bg-white p-4 sm:flex-row sm:items-end"
        >
          <label className="text-xs font-bold text-slate-600">
            Status
            <select
              value={status}
              onChange={(event) => setStatus(event.target.value)}
              className="mt-1 block rounded-lg border px-3 py-2 text-sm font-normal"
            >
              <option value="">All statuses</option>
              <option value="processing">Processing</option>
              <option value="pending">Pending</option>
              <option value="partial">Partial</option>
              <option value="successful">Successful</option>
              <option value="failed">Failed</option>
            </select>
          </label>

          <label className="text-xs font-bold text-slate-600">
            Reference
            <input
              value={reference}
              onChange={(event) => setReference(event.target.value)}
              placeholder="Search reference"
              className="mt-1 block rounded-lg border px-3 py-2 text-sm font-normal"
            />
          </label>

          <button type="submit" className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">
            Filter
          </button>
          <button type="button" onClick={resetFilters} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">
            Reset
          </button>
        </form>

        <div className="mt-4 overflow-x-auto rounded-xl border bg-white">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b bg-slate-50">
                <th className="p-4">Reference</th>
                <th className="p-4">User</th>
                <th className="p-4">Status</th>
                <th className="p-4">Progress</th>
                <th className="p-4">Success</th>
                <th className="p-4">Failed</th>
                <th className="p-4">Action</th>
              </tr>
            </thead>
            <tbody>
              {operations.data.map((operation) => {
                const pending =
                  operation.metadata?.pending_items ??
                  Math.max(
                    0,
                    operation.total_items -
                      operation.successful_items -
                      operation.failed_items,
                  );
                const expanded = openId === operation.id;

                return (
                  <Fragment key={operation.id}>
                    <tr className="border-b last:border-0">
                      <td className="p-4 font-mono text-xs">{operation.reference}</td>
                      <td className="p-4">
                        {operation.user?.name ?? operation.user?.email ?? '—'}
                      </td>
                      <td className="p-4 font-semibold">{operation.status}</td>
                      <td className="p-4">
                        {operation.processed_items}/{operation.total_items}
                      </td>
                      <td className="p-4 text-emerald-700">{operation.successful_items}</td>
                      <td className="p-4 text-red-700">{operation.failed_items}</td>
                      <td className="p-4">
                        <button
                          type="button"
                          onClick={() => setOpenId(expanded ? null : operation.id)}
                          className="rounded-lg border px-2.5 py-1.5 text-xs font-bold text-slate-700"
                        >
                          {expanded ? 'Hide' : 'Details'}
                        </button>
                      </td>
                    </tr>
                    {expanded && (
                      <tr className="border-b bg-slate-50">
                        <td colSpan={7} className="p-4 text-xs text-slate-600">
                          <div className="grid gap-3 sm:grid-cols-3">
                            <div><span className="font-bold">Items:</span> {operation.total_items}</div>
                            <div><span className="font-bold">Processed:</span> {operation.processed_items}</div>
                            <div><span className="font-bold">Pending:</span> {pending}</div>
                          </div>
                          <div className="mt-3">
                            <span className="font-bold">Idempotency:</span>{' '}
                            <span className="font-mono">{operation.idempotency_key ?? '—'}</span>
                          </div>
                          {operation.created_at && (
                            <div className="mt-2">
                              <span className="font-bold">Created:</span> {operation.created_at}
                            </div>
                          )}
                        </td>
                      </tr>
                    )}
                  </Fragment>
                );
              })}
            </tbody>
          </table>

          {!operations.data.length && (
            <div className="p-8 text-center text-sm text-slate-500">
              No bulk operations found.
            </div>
          )}
        </div>

        {operations.links && operations.links.length > 3 && (
          <div className="mt-4 flex flex-wrap gap-1">
            {operations.links.map((link, index) => (
              <button
                key={`${link.label}-${index}`}
                type="button"
                disabled={!link.url}
                onClick={() =>
                  link.url &&
                  router.get(link.url, {}, { preserveState: true, replace: true })
                }
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${
                  link.active ? 'bg-slate-900 text-white' : 'border bg-white text-slate-700'
                } disabled:opacity-40`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </div>
    </>
  );
}
