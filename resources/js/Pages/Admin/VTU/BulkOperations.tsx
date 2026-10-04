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
  user?: { name?: string | null; email?: string | null } | null;
};

export default function BulkOperations({ operations }: { operations: { data: Operation[] } }) {
  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold">VTU Bulk Operations</h1>
      <p className="mt-1 text-sm text-slate-500">Monitor bulk airtime, data and SMS operations without exposing provider secrets.</p>
      <div className="mt-6 overflow-x-auto rounded-xl border bg-white">
        <table className="w-full text-left text-sm">
          <thead><tr className="border-b bg-slate-50">
            <th className="p-4">Reference</th><th className="p-4">User</th><th className="p-4">Status</th>
            <th className="p-4">Progress</th><th className="p-4">Success</th><th className="p-4">Failed</th>
          </tr></thead>
          <tbody>
            {operations.data.map(op => (
              <tr key={op.id} className="border-b last:border-0">
                <td className="p-4 font-mono text-xs">{op.reference}</td>
                <td className="p-4">{op.user?.name ?? op.user?.email ?? '—'}</td>
                <td className="p-4 font-semibold">{op.status}</td>
                <td className="p-4">{op.processed_items}/{op.total_items}</td>
                <td className="p-4 text-emerald-700">{op.successful_items}</td>
                <td className="p-4 text-red-700">{op.failed_items}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {!operations.data.length && <div className="p-8 text-center text-sm text-slate-500">No bulk operations yet.</div>}
      </div>
    </div>
  );
}
