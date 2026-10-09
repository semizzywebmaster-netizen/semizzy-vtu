import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Product={id:number;name:string;category:string;price_minor:string|number;currency:string};
type Package={id:number;name:string;description?:string|null;duration_days:number;price_minor:string|number;currency:string;priority_weight:number};
type Promotion={id:number;target_label?:string|null;status:string;payment_status:string;price_minor:string|number;currency:string;created_at:string;admin_note?:string|null};
type Props={products:Product[];packages:Package[];requests:Promotion[]};
function csrf(){const c=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));return c?decodeURIComponent(c.split('=').slice(1).join('=')):'';}
function money(v:string|number,currency='NGN'){return new Intl.NumberFormat('en-NG',{style:'currency',currency,maximumFractionDigits:2}).format(Number(v||0)/100);}
export default function Index({products,packages,requests}:Props){
 const [productId,setProductId]=useState(products[0]?.id?String(products[0].id):'');
 const [packageId,setPackageId]=useState(packages[0]?.id?String(packages[0].id):'');
 const [note,setNote]=useState('');
 const [message,setMessage]=useState('');
 const [busy,setBusy]=useState(false);
 const submit=async()=>{if(!productId||!packageId){setMessage('Select one of your active listings and an available package.');return;}setBusy(true);setMessage('');try{const res=await fetch('/ads/promotions',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':csrf()},body:JSON.stringify({product_id:Number(productId),package_id:Number(packageId),advertiser_note:note})});const data=await res.json();setMessage(data.message||'Request submitted');if(res.ok)window.location.reload();}catch{setMessage('Could not submit the boost request.');}finally{setBusy(false);}};
 return <><Head title="Boost a Marketplace Listing"/><main className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
 <header><p className="text-sm font-semibold uppercase tracking-wide text-slate-500">SEMIZZY ONE · Marketplace</p><h1 className="mt-1 text-2xl font-bold">Boost a listing</h1><p className="mt-2 max-w-3xl text-sm text-slate-500">Request a sponsored placement for an active listing you own. All boosts require review and confirmed payment before activation.</p></header>
 {message&&<div role="status" className="rounded-lg border p-3 text-sm">{message}</div>}
 <section className="space-y-4 rounded-xl border p-4 md:p-5"><h2 className="text-lg font-bold">Create a boost request</h2>
 {products.length===0?<p className="rounded-lg border p-4 text-sm text-slate-500">You do not currently have any active listings eligible for promotion. Publish a listing first.</p>:packages.length===0?<p className="rounded-lg border p-4 text-sm text-slate-500">There are no active promotion packages available right now.</p>:<>
 <label className="block text-sm font-medium">Your active listing<select value={productId} onChange={e=>setProductId(e.target.value)} className="mt-1 min-h-11 w-full rounded-lg border px-3">{products.map(p=><option key={p.id} value={p.id}>{p.name} · {p.category}</option>)}</select></label>
 <div className="grid gap-3 md:grid-cols-2">{packages.map(p=><label key={p.id} className={`block cursor-pointer rounded-xl border p-4 ${String(p.id)===packageId?'border-slate-950 ring-1 ring-slate-950':''}`}><div className="flex items-start gap-3"><input type="radio" name="package" checked={String(p.id)===packageId} onChange={()=>setPackageId(String(p.id))} className="mt-1"/><div className="flex-1"><div className="flex flex-wrap items-start justify-between gap-2"><b>{p.name}</b><b>{money(p.price_minor,p.currency)}</b></div><p className="mt-1 text-sm text-slate-500">{p.description||'Sponsored listing promotion'}</p><p className="mt-2 text-xs text-slate-500">{p.duration_days} days · Priority weight {p.priority_weight}</p></div></div></label>)}</div>
 <label className="block text-sm font-medium">Note to the review team (optional)<textarea value={note} onChange={e=>setNote(e.target.value)} maxLength={2000} rows={3} className="mt-1 w-full rounded-lg border p-3" placeholder="Tell us about your promotion request."/></label>
 <button disabled={busy} onClick={submit} className="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{busy?'Submitting…':'Submit boost request'}</button>
 <p className="text-xs text-slate-500">This form does not collect payment. Your request remains unpaid and cannot be approved for delivery until a real payment integration confirms settlement.</p>
 </>}
 </section>
 <section className="space-y-3 rounded-xl border p-4 md:p-5"><h2 className="text-lg font-bold">Your boost requests</h2>{requests.length===0?<p className="text-sm text-slate-500">You have not submitted any boost requests.</p>:<div className="space-y-3">{requests.map(r=><article key={r.id} className="rounded-lg border p-4"><div className="flex flex-wrap items-start justify-between gap-2"><div><b>{r.target_label||'Marketplace listing'}</b><p className="mt-1 text-xs text-slate-500">Request #{r.id} · {new Date(r.created_at).toLocaleString()}</p></div><div className="flex flex-wrap gap-2 text-xs"><span className="rounded-full border px-2 py-1">{r.status.replaceAll('_',' ')}</span><span className="rounded-full border px-2 py-1">Payment: {r.payment_status}</span></div></div><p className="mt-2 text-sm font-semibold">{money(r.price_minor,r.currency)}</p>{r.admin_note&&<p className="mt-2 text-sm text-slate-500">Review note: {r.admin_note}</p>}</article>)}</div>}</section>
 </main></>;
}
