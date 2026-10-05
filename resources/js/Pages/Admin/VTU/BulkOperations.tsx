type Operation = {
  id: number;
  reference: string;
  status: string;
  total_items: number;
  processed_items: number;
  successful_items: number;
  failed_items: number;
  idempotency_key?: string | null;
  created_at?: string;
  metadata?: { pending_items?: number | null } | null;
  user?: { name?: string | null; email?: string | null } | null;
};

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function BulkOperations({ operations }: { operations: { data: Operation[]; links?: { url: string | null; label: string; active: boolean }[] } }) {
  const [openId, setOpenId] = useState<number | null>(null);
  const [status, setStatus] = useState('');
  const submitFilters = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    router.get('/admin/vtu/bulk', status ? { status } : {}, { preserveState: true, replace: true });
  };
  return (
    <>
      <Head title="VTU Bulk Operations" />
      <div className="p-4 md:p-6">
      <h1 className="text-2xl font-bold">VTU Bulk Operations</h1>
      <p className="mt-1 text-sm text-slate-500">Monitor bulk airtime, data and SMS operations without exposing provider secrets.</p>
      <form onSubmit={submitFilters} className="mt-5 flex flex-col gap-2 rounded-xl border bg-white p-4 sm:flex-row sm:items-end">
        <label className="text-xs font-bold text-slate-600">Status
          <select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1 block rounded-lg border px-3 py-2 text-sm font-normal">
            <option value="">All statuses</option><option value="processing">Processing</option><option value="pending">Pending</option><option value="partial">Partial</option><option value="successful">Successful</option><option value="failed">Failed</option>
          </select>
        </label>
        <button type="submit" className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Filter</button>
        <button type="button" onClick={() => { setStatus(''); router.get('/admin/vtu/bulk', {}, { preserveState: true, replace: true }); }} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">Reset</button>
      </form>
      <div className="mt-4 overflow-x-auto rounded-xl border bg-white">
        <table className="w-full text-left text-sm">
          <thead><tr className="border-b bg-slate-50">
            <th className="p-4">Reference</th><th className="p-4">User</th><th className="p-4">Status</th>
            <th className="p-4">Progress</th><th className="p-4">Success</th><th className="p-4">Failed</th><th className="p-4">Action</th>
          </tr></thead>
          <tbody>
            {operations.data.map(op => (
              <tr key={op.id} className="border-b last:border-0">
                <td className="p-4 font-mono text-xs">{op.reference}</td>
                <td className="p-4">{op.user?.name ?? op.user?.email ?? '—'}</td>
                <td className="p-4 font-semibold">{op.status}</td>
                <td className="p-4">{op.processed_items}/{op.total_items}</td>
                <td className="p-4 text-emerald-700">{op.successful_items}</td>
                <td className="p-4 text-red-700">{op.failed_items}</td><td className="p-4"><button type="button" onClick={() => setOpenId(openId === op.id ? null : op.id)} className="rounded-lg border px-2.5 py-1.5 text-xs font-bold text-slate-700">{openId === op.id ? 'Hide' : 'Details'}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        {!operations.data.length && <div className="p-8 text-center text-sm text-slate-500">No bulk operations yet.</div>}
      </div>
    </div>
  );
}
