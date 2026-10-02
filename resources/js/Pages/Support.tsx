import { Head, router, useForm } from '@inertiajs/react';

type Ticket = { id: number; reference: string; subject: string; category: string; priority: string; status: string; messageCount: number; updatedAt: string | null; requester: string | null };
type Props = { tickets: { data: Ticket[]; links: { url: string | null; label: string; active: boolean }[] } };

export default function Support({ tickets }: Props) {
  const form = useForm({ subject: '', category: 'general', message: '' });
  const submit = (event: React.FormEvent) => { event.preventDefault(); form.post('/support', { preserveScroll: true, onSuccess: () => form.reset() }); };
  return <><Head title="Support" /><main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8"><div className="mx-auto max-w-4xl">
    <p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Support centre</h1><p className="mt-2 text-sm text-slate-600">Create a ticket and keep replies together. Never include passwords, API secrets, or one-time codes.</p>
    <form onSubmit={submit} className="mt-6 space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
      <h2 className="font-bold">Create a support ticket</h2>
      <input className="w-full rounded-xl border border-slate-300 p-3" placeholder="Subject" maxLength={160} value={form.data.subject} onChange={e=>form.setData('subject',e.target.value)} required />
      {form.errors.subject && <p className="text-sm text-red-600">{form.errors.subject}</p>}
      <select className="w-full rounded-xl border border-slate-300 p-3" value={form.data.category} onChange={e=>form.setData('category',e.target.value)}><option value="general">General</option><option value="account">Account</option><option value="service">Service</option><option value="provider">Provider</option><option value="security">Security</option><option value="other">Other</option></select>
      <textarea className="min-h-28 w-full rounded-xl border border-slate-300 p-3" placeholder="Describe the issue" minLength={5} maxLength={10000} value={form.data.message} onChange={e=>form.setData('message',e.target.value)} required />
      {form.errors.message && <p className="text-sm text-red-600">{form.errors.message}</p>}
      <button disabled={form.processing} className="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing ? 'Submitting…' : 'Submit ticket'}</button>
    </form>
    <section className="mt-8"><h2 className="text-lg font-bold">Your tickets</h2><div className="mt-3 space-y-3">{tickets.data.length ? tickets.data.map(ticket=><a key={ticket.id} href={`/support/${ticket.id}`} className="block rounded-2xl border border-slate-200 bg-white p-4 hover:border-indigo-300"><div className="flex flex-wrap items-start justify-between gap-2"><div><p className="text-xs font-semibold text-slate-500">{ticket.reference}{ticket.requester ? ` · ${ticket.requester}` : ''}</p><h3 className="mt-1 font-bold text-slate-900">{ticket.subject}</h3></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-700">{ticket.status}</span></div><p className="mt-2 text-sm text-slate-600">{ticket.messageCount} message{ticket.messageCount===1?'':'s'} · {ticket.category}</p></a>) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">No support tickets yet.</div>}</div>
      <div className="mt-4 flex flex-wrap gap-2">{tickets.links.map((link,i)=><button key={i} disabled={!link.url} onClick={()=>link.url&&router.visit(link.url)} className={`rounded-lg border px-3 py-2 text-sm ${link.active?'border-indigo-600 bg-indigo-50':'border-slate-200 bg-white'} disabled:opacity-40`} dangerouslySetInnerHTML={{__html:link.label}} />)}</div>
    </section>
    <nav className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 p-2 backdrop-blur md:static md:mt-8 md:border-0"><div className="mx-auto flex max-w-4xl justify-around text-xs font-semibold text-slate-600 md:justify-start md:gap-5"><a href="/dashboard" className="p-2">Home</a><a href="/dashboard" className="p-2">Services</a><a href="/dashboard" className="p-2">Transactions</a><a href="/notifications" className="p-2">Notifications</a><a href="/profile" className="p-2">Profile</a></div></nav>
  </div></main></>;
}
