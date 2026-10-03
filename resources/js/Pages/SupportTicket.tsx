import { Head, useForm, usePage } from '@inertiajs/react';
import CoreMobileNav from '../Components/CoreMobileNav';

type SharedProps = { navigation?: { unreadNotifications?: number } };

type Message = { id: number; author: string; staff: boolean; message: string; createdAt: string | null };
type Ticket = { id: number; reference: string; subject: string; category: string; priority: string; status: string; requester: string | null; messages: Message[] };
type Props = { ticket: Ticket; canManage: boolean };

export default function SupportTicketPage({ ticket, canManage }: Props) {
  const unreadCount = usePage<SharedProps>().props.navigation?.unreadNotifications ?? 0;
  const reply = useForm({ message: '' });
  const status = useForm({ status: ticket.status });
  const submitReply = (event: React.FormEvent) => { event.preventDefault(); reply.post(`/support/${ticket.id}/reply`, { preserveScroll: true, onSuccess: () => reply.reset() }); };
  const updateStatus = (event: React.FormEvent) => { event.preventDefault(); status.patch(`/support/${ticket.id}/status`, { preserveScroll: true }); };
  return <><Head title={ticket.reference} /><main className="min-h-screen bg-slate-50 p-4 pb-10 md:p-8"><div className="mx-auto max-w-3xl">
    <a href="/support" className="text-sm font-semibold text-indigo-700">← Back to support</a><header className="mt-4 rounded-2xl border border-slate-200 bg-white p-5"><p className="text-xs font-semibold text-slate-500">{ticket.reference}{ticket.requester ? ` · ${ticket.requester}` : ''}</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">{ticket.subject}</h1><p className="mt-2 text-sm text-slate-600">Category: {ticket.category} · Priority: {ticket.priority} · Status: {ticket.status}</p>
    {canManage && <form onSubmit={updateStatus} className="mt-4 flex gap-2"><select className="rounded-xl border border-slate-300 p-2" value={status.data.status} onChange={e=>status.setData('status',e.target.value)}>{['open','pending','resolved','closed'].map(s=><option key={s} value={s}>{s}</option>)}</select><button disabled={status.processing} className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Update status</button></form>}
    </header>
    <section className="mt-5 space-y-3">{ticket.messages.map(m=><article key={m.id} className={`rounded-2xl border bg-white p-4 ${m.staff?'border-indigo-200':'border-slate-200'}`}><div className="flex justify-between gap-3"><p className="font-bold text-slate-900">{m.author}{m.staff?' · Support':''}</p>{m.createdAt&&<time className="text-xs text-slate-500" dateTime={m.createdAt}>{new Date(m.createdAt).toLocaleString()}</time>}</div><p className="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{m.message}</p></article>)}</section>
    {(ticket.status!=='closed'&&ticket.status!=='resolved'||canManage) && <form onSubmit={submitReply} className="mt-5 rounded-2xl border border-slate-200 bg-white p-5"><h2 className="font-bold">Add a reply</h2><textarea className="mt-3 min-h-28 w-full rounded-xl border border-slate-300 p-3" value={reply.data.message} onChange={e=>reply.setData('message',e.target.value)} minLength={2} maxLength={10000} required placeholder="Write your reply…" />{reply.errors.message&&<p className="mt-1 text-sm text-red-600">{reply.errors.message}</p>}<button disabled={reply.processing} className="mt-3 rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:opacity-50">Send reply</button></form>}
  </div><CoreMobileNav active="support" unreadCount={unreadCount} /></main></>;
}
