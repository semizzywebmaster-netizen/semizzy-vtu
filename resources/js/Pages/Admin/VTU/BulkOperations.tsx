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
  archived_at?: string | null;
  scheduled_at?: string | null;
  edit_until?: string | null;
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
  const [reconcilingId, setReconcilingId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkReconciling, setBulkReconciling] = useState(false);
  const [bulkAuditing, setBulkAuditing] = useState(false);
  const [recovering, setRecovering] = useState(false);
  const [auditId, setAuditId] = useState<number | null>(null);
  const [auditResult, setAuditResult] = useState<any>(null);
  const [cancellingId, setCancellingId] = useState<number | null>(null);
  const [requeryingId, setRequeryingId] = useState<number | null>(null);

  const submitFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const params: Record<string, string> = {};
    if (status) params.status = status;
    if (reference.trim()) params.reference = reference.trim();
    router.get('/admin/vtu/bulk', params, { preserveState: true, replace: true });
  };

  const reconcile = (id: number) => {
    setReconcilingId(id);
    router.post(`/admin/vtu/bulk/${id}/reconcile`, {}, {
      preserveScroll: true,
      onFinish: () => setReconcilingId(null),
    });
  };

  const reconcileSelected = () => {
    if (!selectedIds.length) return;
    setBulkReconciling(true);
    router.post('/admin/vtu/bulk/reconcile-selected', { bulk_ids: selectedIds }, {
      preserveScroll: true,
      onSuccess: () => setSelectedIds([]),
      onFinish: () => setBulkReconciling(false),
    });
  };

  const archiveSelected = () => {
    if (!selectedIds.length) return;
    if (!window.confirm('Archive the selected terminal bulk operations? Financial records and audit history will be preserved. Non-terminal operations will be skipped.')) return;
    router.post('/admin/vtu/bulk/archive-selected', { bulk_ids: selectedIds }, {
      preserveScroll: true,
      onSuccess: () => setSelectedIds([]),
    });
  };

  const exportSelected = () => {
    if (!selectedIds.length) return;
    const params = selectedIds.map((id) => `bulk_ids[]=${encodeURIComponent(String(id))}`).join('&');
    window.location.href = '/admin/vtu/bulk/export-selected?' + params;
  };

  const unarchive = async (id: number) => {
    if (!window.confirm('Restore this archived bulk operation to active history?')) return;
    try {
      const response = await fetch('/admin/vtu/bulk/' + id + '/unarchive', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Bulk unarchive failed.');
      window.location.reload();
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Bulk unarchive failed.');
    }
  };

  const auditSelected = async () => {
    if (!selectedIds.length) return;
    setBulkAuditing(true);
    try {
      const response = await fetch('/admin/vtu/bulk/audit-selected', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' }, body: JSON.stringify({ bulk_ids: selectedIds }) });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Bulk integrity audit failed.');
      window.alert(\`Integrity audit: \${payload.healthy ?? 0} healthy, \${payload.with_issues ?? 0} requiring attention.\`);
    } catch (error) { window.alert(error instanceof Error ? error.message : 'Bulk integrity audit failed.'); }
    finally { setBulkAuditing(false); }
  };

  const recoverStale = () => {
    setRecovering(true);
    router.post('/admin/vtu/bulk/recover-stale', { limit: 50, stale_minutes: 10 }, { preserveScroll: true, onFinish: () => setRecovering(false) });
  };

  const audit = async (id: number) => {
    setAuditId(id); setAuditResult(null);
    try {
      const res = await fetch(`/admin/vtu/bulk/${id}/audit`, { headers: { Accept: 'application/json' } });
      const json = await res.json();
      setAuditResult(json);
    } catch { setAuditResult({ healthy: false, issue_count: 1, issues: [{ message: 'Audit request failed.' }] }); }
    finally { setAuditId(null); }
  };

  const cancelBulk = (id: number) => {
    const reason = window.prompt('Cancellation reason');
    if (!reason?.trim()) return;
    setCancellingId(id);
    router.post(`/admin/vtu/bulk/${id}/cancel`, { reason: reason.trim() }, { preserveScroll: true, onFinish: () => setCancellingId(null) });
  };

  const requeryPending = (id: number) => {
    setRequeryingId(id);
    router.post(`/admin/vtu/bulk/${id}/requery-items`, {}, { preserveScroll: true, onFinish: () => setRequeryingId(null) });
  };

  const toggleSelected = (id: number) => {
    setSelectedIds((current) => current.includes(id) ? current.filter((value) => value !== id) : [...current, id]);
  };

  const selectableIds = operations.data
    .filter((operation) => (operation.metadata?.pending_items ?? Math.max(0, operation.total_items - operation.successful_items - operation.failed_items)) > 0)
    .map((operation) => operation.id);

  const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selectedIds.includes(id));

  const toggleAll = () => {
    setSelectedIds(allSelected ? [] : selectableIds);
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

        <div className="mt-4 rounded-xl border bg-white p-3">
          <div className="flex flex-wrap items-center gap-2">
            <a href="/admin/vtu/schedule-policy" className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">Schedule policy</a><a href={`/admin/vtu/bulk/export?status=${encodeURIComponent(status)}&reference=${encodeURIComponent(reference)}`} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">
              Export CSV
            </a>
              {operations.data.find((operation) => operation.id === openId)?.archived_at ? (
                <button type="button" onClick={() => unarchive(openId!)} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">
                  Unarchive
                </button>
              ) : (
                <button type="button" onClick={()=>{if(confirm('Archive this completed bulk operation? Financial records and audit history will be preserved.')) fetch(`/admin/vtu/bulk/${openId}/archive`,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'','Accept':'application/json'}}).then(()=>window.location.reload())}} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">
                  Archive
                </button>
              )}
              <a href={`/admin/vtu/bulk/${openId}/statement`} target="_blank" rel="noreferrer" className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">Statement / Print</a>
              <a href={`/admin/vtu/bulk/${openId}/report`} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">
                Report CSV
              </a>

            <button type="button" onClick={exportSelected} disabled={!selectedIds.length} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">Export selected ({selectedIds.length})</button>
            <button type="button" onClick={archiveSelected} disabled={!selectedIds.length} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">Archive selected ({selectedIds.length})</button>
            <button type="button" onClick={toggleAll} disabled={!selectableIds.length} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">{allSelected ? 'Clear selection' : 'Select pending'}</button>
            <button type="button" onClick={auditSelected} disabled={!selectedIds.length || bulkAuditing} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">{bulkAuditing ? 'Auditing selected…' : `Audit selected (${selectedIds.length})`}</button>
            <button type="button" onClick={reconcileSelected} disabled={!selectedIds.length || bulkReconciling} className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white disabled:opacity-40">{bulkReconciling ? 'Reconciling selected…' : `Reconcile selected (${selectedIds.length})`}</button>
            <button type="button" onClick={recoverStale} disabled={recovering} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">{recovering ? 'Recovering stale…' : 'Recover stale operations'}</button>
            {selectedIds.length > 0 && <span className="text-xs text-slate-500">{selectedIds.length} bulk operation(s) selected</span>}
          </div>
        </div>

        <div className="mt-4 overflow-x-auto rounded-xl border bg-white">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b bg-slate-50">
                <th className="p-4"><input type="checkbox" aria-label="Select all pending bulk operations" checked={allSelected} onChange={toggleAll} disabled={!selectableIds.length} /></th>
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
                      <td className="p-4"><input type="checkbox" aria-label={`Select ${operation.reference}`} checked={selectedIds.includes(operation.id)} onChange={() => toggleSelected(operation.id)} disabled={pending === 0} /></td>
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
                        <td colSpan={8} className="p-4 text-xs text-slate-600">
                          <div className="grid gap-3 sm:grid-cols-3">
                            <div><span className="font-bold">Items:</span> {operation.total_items}</div>
                            <div><span className="font-bold">Processed:</span> {operation.processed_items}</div>
                            <div><span className="font-bold">Pending:</span> {pending}</div><div><span className="font-bold">Executes:</span> {operation.scheduled_at ? new Date(operation.scheduled_at).toLocaleString() : 'Immediate'}</div><div><span className="font-bold">Editing closes:</span> {operation.edit_until ? new Date(operation.edit_until).toLocaleString() : '—'}</div>
                          </div>
                          <div className="mt-3">
                            <span className="font-bold">Idempotency:</span>{' '}
                            <span className="font-mono">{operation.idempotency_key ?? '—'}</span>
                          </div>
                          {pending > 0 && (
                            <div className="mt-4">
                              <button
                                type="button"
                                onClick={() => audit(operation.id)}
                                disabled={auditId === operation.id}
                                className="mr-2 rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50"
                              >
                                {auditId === operation.id ? 'Auditing…' : 'Audit integrity'}
                              </button>
                              <button type="button" onClick={() => requeryPending(operation.id)} disabled={requeryingId === operation.id} className="ml-2 rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50">
                                {requeryingId === operation.id ? 'Requerying…' : 'Requery pending'}
                              </button>
                              <button
                                type="button"
                                onClick={() => cancelBulk(operation.id)}
                                disabled={cancellingId === operation.id || ['successful','failed','partial','cancelled'].includes(operation.status)}
                                className="ml-2 rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 disabled:opacity-50"
                              >
                                {cancellingId === operation.id ? 'Cancelling…' : 'Cancel pending'}
                              </button>
                              <button
                                type="button"
                                onClick={() => reconcile(operation.id)}
                                disabled={reconcilingId === operation.id}
                                className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50"
                              >
                                {reconcilingId === operation.id ? 'Reconciling…' : 'Reconcile Pending'}
                              </button>
                            </div>
                          )}
                          {auditResult && auditResult.bulk_operation_id === operation.id && (
                            <div className={`mt-4 rounded-lg border p-3 ${auditResult.healthy ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'}`}>
                              <div className="font-bold">Integrity audit: {auditResult.healthy ? 'Healthy' : `${auditResult.issue_count} issue(s)`}</div>
                              {!auditResult.healthy && <ul className="mt-2 list-disc pl-5">{(auditResult.issues || []).slice(0, 10).map((issue: any, index: number) => <li key={index}>{issue.code}: {issue.message}</li>)}</ul>}
                            </div>
                          )}
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
