import { Head, router, useForm } from '@inertiajs/react';

type AuditEvent = {
  id: number; event: string; actor: string; actorId: number | null;
  subjectType: string | null; subjectId: number | null; requestId: string | null;
  ipAddress: string | null; context: Record<string, unknown>; createdAt: string | null;
};
type LinkItem = { url: string | null; label: string; active: boolean };
type Props = { events: { data: AuditEvent[]; links: LinkItem[]; total: number }; filters: { event?: string; actor_id?: number | string; request_id?: string; from?: string; to?: string } };

export default function AuditEvents({ events, filters }: Props) {
  const form = useForm({ event: filters.event ?? '', actor_id: filters.actor_id?.toString() ?? '', request_id: filters.request_id ?? '', from: filters.from ?? '', to: filters.to ?? '' });
  const submit = (e: React.FormEvent) => { e.preventDefault(); router.get('/admin/audit-events', form.data, { preserveState: true, preserveScroll: true, replace: true }); };

  return <><Head title="Audit events" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <header><p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Audit events</h1><p className="mt-1 text-sm text-slate-600">{events.total} matching events. Sensitive values are redacted; this page is read-only.</p></header>
    <form onSubmit={submit} className="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
      <input className="rounded-xl border border-slate-300 p-3" placeholder="Event contains…" value={form.data.event} onChange={e=>form.setData('event',e.target.value)} />
      <input className="rounded-xl border border-slate-300 p-3" placeholder="Actor user ID" inputMode="numeric" value={form.data.actor_id} onChange={e=>form.setData('actor_id',e.target.value)} />
      <input className="rounded-xl border border-slate-300 p-3" placeholder="Request ID" value={form.data.request_id} onChange={e=>form.setData('request_id',e.target.value)} />
      <input className="rounded-xl border border-slate-300 p-3" type="date" value={form.data.from} onChange={e=>form.setData('from',e.target.value)} />
      <input className="rounded-xl border border-slate-300 p-3" type="date" value={form.data.to} onChange={e=>form.setData('to',e.target.value)} />
      <button className="rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white sm:col-span-2 lg:col-span-5">Filter events</button>
    </form>
    <section className="mt-5 space-y-3">{events.data.length ? events.data.map(event=><article key={event.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-2"><div><h2 className="font-bold text-slate-900">{event.event}</h2><p className="mt-1 text-sm text-slate-600">Actor: {event.actor}{event.actorId ? ` (#${event.actorId})` : ''} · {event.subjectType ?? 'System'}{event.subjectId ? ` #${event.subjectId}` : ''}</p></div><time className="text-xs text-slate-500">{event.createdAt ? new Date(event.createdAt).toLocaleString() : '—'}</time></div><div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">{event.ipAddress && <span>IP: {event.ipAddress}</span>}{event.requestId && <span className="break-all">Request: {event.requestId}</span>}</div>{Object.keys(event.context).length > 0 && <pre className="mt-3 overflow-x-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-700">{JSON.stringify(event.context, null, 2)}</pre>}</article>) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">No audit events match these filters.</div>}</section>
    <div className="mt-4 flex flex-wrap gap-2">{events.links.map((link,i)=><button key={i} disabled={!link.url} onClick={()=>link.url&&router.visit(link.url)} className={`rounded-lg border px-3 py-2 text-sm ${link.active?'border-indigo-600 bg-indigo-50':'border-slate-200 bg-white'} disabled:opacity-40`} dangerouslySetInnerHTML={{__html:link.label}} />)}</div>
    <nav className="mt-8 flex flex-wrap gap-4 text-sm font-semibold"><a href="/dashboard">Dashboard</a><a href="/admin/security-events">Security events</a><a href="/admin/users">Users & staff</a></nav>
  </div></main></>;
}
