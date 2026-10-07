import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Service={id:number;type:string;name:string;code:string;description?:string|null;enabled:boolean};
type Booking={id:number;type:string;status:string;total:string|number;currency:string;provider_code?:string|null;provider_reference?:string|null;booking_reference?:string|null;user_id:number;created_at?:string};
type Refund={id:number;status:string;amount:string|number;booking?:Booking|null;created_at?:string};
type Page<T>={data:T[]};

const money=(v:string|number,c='NGN')=>(c==='NGN'?'₦':c+' ')+Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
const badge=(s:string)=>s==='confirmed'||s==='approved'?'bg-emerald-100 text-emerald-700':s==='failed'||s==='cancelled'?'bg-red-100 text-red-700':s==='provider_pending'||s==='pending'?'bg-amber-100 text-amber-800':'bg-slate-100 text-slate-700';

export default function TravelTickets({services=[],bookings,refunds}:{services:Service[];bookings:Page<Booking>;refunds:Page<Refund>}){
 const service=useForm({type:'flight',name:'',code:'',description:'',enabled:true,requirements:{}});
 const[tab,setTab]=useState<'services'|'bookings'|'refunds'>('services');
 const[busy,setBusy]=useState<string|null>(null);
 const submit=(e:FormEvent)=>{e.preventDefault();service.post('/admin/travel-tickets/services',{preserveScroll:true,onSuccess:()=>service.reset()})};
 const toggle=(id:number)=>router.post(`/admin/travel-tickets/services/${id}/toggle`,{},{preserveScroll:true});
 const requery=(id:number)=>{setBusy(`requery-${id}`);router.post(`/admin/travel-tickets/bookings/${id}/requery`,{},{preserveScroll:true,onFinish:()=>setBusy(null)})};
 const requestRefund=(id:number)=>{if(!window.confirm('Create a refund request for this cancelled booking?'))return;setBusy(`refund-${id}`);router.post(`/admin/travel-tickets/bookings/${id}/refund`,{},{preserveScroll:true,onFinish:()=>setBusy(null)})};
 const approve=(id:number)=>{setBusy(`approve-${id}`);router.post(`/admin/travel-tickets/refunds/${id}/approve`,{},{preserveScroll:true,onFinish:()=>setBusy(null)})};
 return <main className="min-h-screen bg-slate-50 p-4 pb-24 text-slate-900 sm:p-8"><Head title="Travel & Tickets Admin"/><div className="mx-auto max-w-7xl">
  <header><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Addon management</p><h1 className="mt-1 text-2xl font-black">Travel & Tickets</h1><p className="mt-1 text-sm text-slate-500">Manage services, reconcile pending bookings and approve eligible refunds.</p></header>
  <div className="mt-6 grid gap-3 sm:grid-cols-3">
   <button onClick={()=>setTab('services')} className={'rounded-2xl border p-4 text-left '+(tab==='services'?'border-indigo-500 bg-indigo-50':'bg-white')}><b>Services</b><span className="block text-xs text-slate-500">{services.length} configured</span></button>
   <button onClick={()=>setTab('bookings')} className={'rounded-2xl border p-4 text-left '+(tab==='bookings'?'border-indigo-500 bg-indigo-50':'bg-white')}><b>Bookings</b><span className="block text-xs text-slate-500">{bookings?.data?.length||0} shown</span></button>
   <button onClick={()=>setTab('refunds')} className={'rounded-2xl border p-4 text-left '+(tab==='refunds'?'border-indigo-500 bg-indigo-50':'bg-white')}><b>Refunds</b><span className="block text-xs text-slate-500">{refunds?.data?.length||0} shown</span></button>
  </div>
  {tab==='services'&&<section className="mt-6 grid gap-5 lg:grid-cols-[1fr_1.5fr]">
   <form onSubmit={submit} className="rounded-2xl border bg-white p-5 shadow-sm"><h2 className="font-black">Add travel service</h2><div className="mt-4 space-y-3">
    <select className="w-full rounded-xl border p-3" value={service.data.type} onChange={e=>service.setData('type',e.target.value)}><option value="flight">Flight</option><option value="bus">Bus</option><option value="hotel">Hotel</option></select>
    <input required className="w-full rounded-xl border p-3" placeholder="Service name" value={service.data.name} onChange={e=>service.setData('name',e.target.value)}/>
    <input required className="w-full rounded-xl border p-3" placeholder="Unique service code" value={service.data.code} onChange={e=>service.setData('code',e.target.value)}/>
    <textarea className="min-h-24 w-full rounded-xl border p-3" placeholder="Description" value={service.data.description} onChange={e=>service.setData('description',e.target.value)}/>
    <label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={service.data.enabled} onChange={e=>service.setData('enabled',e.target.checked)}/> Enabled immediately</label>
    <button disabled={service.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-black text-white">{service.processing?'Saving…':'Create service'}</button>
   </div></form>
   <div className="space-y-3">{services.map(s=><article key={s.id} className="rounded-2xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div><span className="text-[10px] font-black uppercase text-indigo-600">{s.type}</span><h3 className="font-black">{s.name}</h3><p className="text-xs text-slate-500">{s.code}</p></div><button onClick={()=>toggle(s.id)} className={'rounded-xl px-3 py-2 text-xs font-black '+(s.enabled?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600')}>{s.enabled?'Enabled':'Disabled'} · Toggle</button></div>{s.description&&<p className="mt-3 text-sm text-slate-600">{s.description}</p>}</article>)}</div>
  </section>}
  {tab==='bookings'&&<section className="mt-6 space-y-3">
   {bookings?.data?.length?bookings.data.map(b=><article key={b.id} className="rounded-2xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap justify-between gap-3"><div><b>#{b.id} · {b.type}</b><span className={'ml-2 rounded-full px-2 py-1 text-[10px] font-black uppercase '+badge(b.status)}>{b.status.replace('_',' ')}</span><p className="mt-1 text-xs text-slate-500">User #{b.user_id} · {b.provider_code||'No provider'} · {b.created_at?new Date(b.created_at).toLocaleString():'—'}</p></div><strong>{money(b.total,b.currency)}</strong></div><div className="mt-3 grid gap-2 text-xs sm:grid-cols-2"><div>Provider ref: <b>{b.provider_reference||'—'}</b></div><div>Booking ref: <b>{b.booking_reference||'—'}</b></div></div>
    <div className="mt-4 flex flex-wrap gap-2">
     {b.status==='provider_pending'&&<button disabled={busy===`requery-${b.id}`} onClick={()=>requery(b.id)} className="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black text-white">{busy===`requery-${b.id}`?'Requerying…':'Requery provider'}</button>}
     {b.status==='cancelled'&&<button disabled={busy===`refund-${b.id}`} onClick={()=>requestRefund(b.id)} className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-black text-amber-800">{busy===`refund-${b.id}`?'Requesting…':'Request refund'}</button>}
    </div>
   </article>):<div className="rounded-2xl border border-dashed bg-white p-10 text-center"><h2 className="font-black">No bookings on this page</h2><p className="mt-1 text-sm text-slate-500">Travel bookings will appear here for reconciliation.</p></div>}
  </section>}
  {tab==='refunds'&&<section className="mt-6 space-y-3">{refunds?.data?.length?refunds.data.map(r=><article key={r.id} className="rounded-2xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap items-center justify-between gap-3"><div><b>Refund #{r.id}</b><span className={'ml-2 rounded-full px-2 py-1 text-[10px] font-black uppercase '+badge(r.status)}>{r.status}</span><p className="mt-1 text-xs text-slate-500">Booking #{r.booking?.id||'—'} · {r.created_at?new Date(r.created_at).toLocaleString():'—'}</p></div><strong>{money(r.amount,r.booking?.currency||'NGN')}</strong></div>{r.status==='pending'&&<button disabled={busy===`approve-${r.id}`} onClick={()=>approve(r.id)} className="mt-4 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white">{busy===`approve-${r.id}`?'Approving…':'Approve refund'}</button>}</article>):<div className="rounded-2xl border border-dashed bg-white p-10 text-center"><h2 className="font-black">No refunds on this page</h2></div>}</section>}
 </div></main>
}
