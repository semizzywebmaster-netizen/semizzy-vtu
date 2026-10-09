import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Category = { id:number; name:string; sale_profit_bps:number; sale_profit_fixed_minor:string; parent?:{name:string}|null };
type Earning = { id:number; order_id:number; seller?:{name?:string;username?:string}|null; category?:{name?:string}|null; gross_amount_minor?:string|null; platform_profit_minor?:string|null; seller_net_minor?:string|null; currency:string; status:string; calculation_snapshot?:{configured_percentage_bps?:number;configured_fixed_minor?:string}|null };
type ReconciliationItem = { order_id:number; reference:string; status:string; currency:string; gross_minor:string; earning_status?:string|null; issues:string[] };
type Reconciliation = { checked_orders:number; issue_count:number; items:ReconciliationItem[] };
type Dispute = { id:number; order_id:number; reason:string; description:string; dispute_status:string; resolution?:string|null; resolution_note?:string|null; created_at:string; reference:string; currency:string; total_minor:string; buyer_name:string; seller_name:string };
type Escrow = { id:number; order_id:number; reference:string; currency:string; gross_minor:string; seller_net_minor:string; platform_profit_minor:string; escrow_status:string; buyer_confirmed_at?:string|null; released_at?:string|null; admin_note?:string|null; buyer_name:string; seller_name:string };
type EscrowPolicy = {physical_dispatch_hours:number;service_delivery_hours:number;buyer_confirmation_hours:number;reminders_enabled:boolean;auto_release_enabled:boolean;auto_release_grace_hours:number;max_dispute_open_days:number};
type Props = { products:{data:any[]}; orders:{data:any[]}; categories:Category[]; earnings:{data:Earning[]}; reconciliation:Reconciliation; escrows:Escrow[]; disputes:Dispute[]; escrowPolicies:EscrowPolicy|null };

export default function Index({products,orders,categories,earnings,reconciliation,escrows,disputes,escrowPolicies}:Props){
 const [items,setItems]=useState(categories);
 const [saving,setSaving]=useState<number|null>(null);
 const [message,setMessage]=useState('');
 const [policy,setPolicy]=useState<EscrowPolicy>(escrowPolicies||{physical_dispatch_hours:72,service_delivery_hours:168,buyer_confirmation_hours:72,reminders_enabled:true,auto_release_enabled:false,auto_release_grace_hours:48,max_dispute_open_days:14});
 const releaseEscrow=async(orderId:number)=>{
  if(!window.confirm('Release this order escrow to the seller? This action credits the seller wallet.')) return;
  setSaving(orderId);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  const res=await fetch('/admin/marketplace/orders/'+orderId+'/release-escrow',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({admin_note:'Released by admin from Marketplace escrow dashboard'})});
  const data=await res.json();
  setMessage(data.message||'Escrow release request completed');
  setSaving(null);
  if(res.ok) window.location.reload();
 };
 const resolveDispute=async(id:number,resolution:'refund_buyer'|'release_seller'|'awaiting_evidence'|'dismiss')=>{
  const note=window.prompt('Enter the admin decision note (required for audit):');
  if(!note||note.trim().length<5){setMessage('A decision note of at least 5 characters is required.');return;}
  setSaving(id);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  try{
   const res=await fetch('/admin/marketplace/disputes/'+id+'/resolve',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({resolution,resolution_note:note})});
   const data=await res.json();setMessage(data.message||'Dispute action completed');if(res.ok)window.location.reload();
  }catch{setMessage('Unable to process dispute decision. Please retry.');}
  finally{setSaving(null);}
 };
 const savePolicy=async()=>{
  setMessage('');setSaving(-1);
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  try{
   const res=await fetch('/admin/marketplace/escrow-policies',{method:'PATCH',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify(policy)});
   const data=await res.json();setMessage(data.message||'Escrow policy saved');
  }catch{setMessage('Unable to save escrow policy. Please retry.');}
  finally{setSaving(null);}
 };
 const update=(id:number,key:'percent'|'fixed',value:string)=>{
  setItems(items.map(c=>c.id===id?(key==='percent'?{...c,sale_profit_bps:Math.max(0,Math.min(10000,Number(value)*100))}:{...c,sale_profit_fixed_minor:value}):c));
 };
 const save=async(c:Category)=>{
  setSaving(c.id);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  const res=await fetch('/admin/marketplace/categories/'+c.id+'/profit',{method:'PATCH',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({sale_profit_percent:c.sale_profit_bps/100,sale_profit_fixed_minor:c.sale_profit_fixed_minor||'0'})});
  const data=await res.json();
  setMessage(data.message||'Saved');
  setSaving(null);
 };
 return <><Head title="Marketplace Admin"/><div className="space-y-6 p-6">
  <h1 className="text-2xl font-bold">Marketplace</h1>
  <div className="grid gap-4 md:grid-cols-3"><div className="rounded-xl border p-4">Products <b>{products.data.length}</b></div><div className="rounded-xl border p-4">Recent orders <b>{orders.data.length}</b></div><div className="rounded-xl border p-4">Categories <b>{categories.length}</b></div></div>
  <section className="rounded-xl border bg-white p-5"><h2 className="text-lg font-bold">Category sales profit</h2><p className="mt-1 text-sm text-slate-500">Admin controls the percentage and optional fixed amount deducted from each sale. Values are category-specific.</p>{message&&<div className="mt-3 rounded-lg bg-slate-100 p-3 text-sm">{message}</div>}
   <div className="mt-4 space-y-3">{items.map(c=><div key={c.id} className="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_180px_220px_100px] md:items-end"><div><div className="font-semibold">{c.name}</div><div className="text-xs text-slate-500">{c.parent?.name||'Top-level category'}</div></div><label className="text-sm">Percentage<input type="number" min="0" max="100" step="0.01" value={(c.sale_profit_bps/100).toString()} onChange={e=>update(c.id,'percent',e.target.value)} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label><label className="text-sm">Fixed amount (minor)<input type="number" min="0" step="1" value={c.sale_profit_fixed_minor||'0'} onChange={e=>update(c.id,'fixed',e.target.value)} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label><button disabled={saving===c.id} onClick={()=>save(c)} className="min-h-10 rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white disabled:opacity-50">{saving===c.id?'Saving':'Save'}</button></div>)}</div>
  </section>
  <section className="space-y-4 rounded-xl border p-5">
   <div><h2 className="text-lg font-bold">Escrow policies & deadlines</h2><p className="mt-1 text-sm text-slate-500">Configure expected dispatch, service delivery, buyer confirmation and dispute windows. Automatic release is disabled by default; enabling the switch alone does not release funds until a dispute-aware scheduled processor is deployed.</p></div>
   <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    <label className="text-sm">Physical dispatch deadline (hours)<input type="number" min="1" max="720" value={policy.physical_dispatch_hours} onChange={e=>setPolicy({...policy,physical_dispatch_hours:Number(e.target.value)})} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label>
    <label className="text-sm">Service delivery deadline (hours)<input type="number" min="1" max="2160" value={policy.service_delivery_hours} onChange={e=>setPolicy({...policy,service_delivery_hours:Number(e.target.value)})} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label>
    <label className="text-sm">Buyer confirmation window (hours)<input type="number" min="1" max="720" value={policy.buyer_confirmation_hours} onChange={e=>setPolicy({...policy,buyer_confirmation_hours:Number(e.target.value)})} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label>
    <label className="text-sm">Auto-release grace period (hours)<input type="number" min="1" max="720" value={policy.auto_release_grace_hours} onChange={e=>setPolicy({...policy,auto_release_grace_hours:Number(e.target.value)})} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label>
    <label className="text-sm">Dispute window (days)<input type="number" min="1" max="90" value={policy.max_dispute_open_days} onChange={e=>setPolicy({...policy,max_dispute_open_days:Number(e.target.value)})} className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label>
   </div>
   <div className="flex flex-wrap gap-5"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={policy.reminders_enabled} onChange={e=>setPolicy({...policy,reminders_enabled:e.target.checked})}/>Enable deadline reminders</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={policy.auto_release_enabled} onChange={e=>setPolicy({...policy,auto_release_enabled:e.target.checked})}/>Allow automatic release policy (processor required)</label></div>
   <button disabled={saving===-1} onClick={savePolicy} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{saving===-1?'Saving…':'Save escrow policies'}</button>
  </section>
  <section className="space-y-3 rounded-xl border p-5">
   <div><h2 className="text-lg font-bold">Escrow management</h2><p className="mt-1 text-sm text-slate-500">Seller funds remain held after payment. Buyer confirmation records receipt; only an authorised admin release credits the seller's available wallet. Review disputes before releasing.</p></div>
   {escrows.length===0?<p className="rounded-lg border p-4 text-sm text-slate-500">No escrow records yet.</p>:<div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Order</th><th className="p-3 text-left">Buyer / Seller</th><th className="p-3 text-left">Gross held</th><th className="p-3 text-left">Seller net</th><th className="p-3 text-left">Escrow status</th><th className="p-3 text-left">Admin action</th></tr></thead><tbody>{escrows.map(e=><tr key={e.id} className="border-t align-top"><td className="p-3"><div className="font-medium">{e.reference}</div><div className="text-xs text-slate-500">Order #{e.order_id}</div></td><td className="p-3">{e.buyer_name}<div className="text-xs text-slate-500">Seller: {e.seller_name}</div></td><td className="p-3">{e.currency} {(Number(e.gross_minor||'0')/100).toFixed(2)}</td><td className="p-3">{e.currency} {(Number(e.seller_net_minor||'0')/100).toFixed(2)}</td><td className="p-3"><div>{e.escrow_status}</div>{e.buyer_confirmed_at&&<div className="text-xs text-slate-500">Buyer confirmed</div>}</td><td className="p-3">{e.escrow_status==='held'||e.escrow_status==='buyer_confirmed'?<button disabled={saving===e.order_id} onClick={()=>releaseEscrow(e.order_id)} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{saving===e.order_id?'Processing…':'Release to seller'}</button>:<span className="text-xs text-slate-500">{e.escrow_status==='released'?'Released':e.escrow_status}</span>}</td></tr>)}</tbody></table></div>}
  </section>
  <section className="space-y-3 rounded-xl border p-5">
   <div><h2 className="text-lg font-bold">Buyer disputes</h2><p className="mt-1 text-sm text-slate-500">Review evidence and order context before deciding. Refunds and releases are recorded in the financial ledger; active disputes block normal escrow release.</p></div>
   {disputes.length===0?<p className="rounded-lg border p-4 text-sm text-slate-500">No disputes have been submitted.</p>:<div className="space-y-3">{disputes.map(d=><article key={d.id} className="rounded-xl border p-4"><div className="flex flex-wrap items-start justify-between gap-2"><div><div className="font-semibold">{d.reference} · {d.reason.replaceAll('_',' ')}</div><div className="mt-1 text-xs text-slate-500">Buyer: {d.buyer_name} · Seller: {d.seller_name} · Dispute #{d.id}</div></div><span className="rounded-full border px-3 py-1 text-xs">{d.dispute_status}</span></div><p className="mt-3 whitespace-pre-wrap text-sm">{d.description}</p>{d.resolution_note&&<p className="mt-2 text-xs text-slate-500">Previous note: {d.resolution_note}</p>}
   {['open','under_review','awaiting_evidence'].includes(d.dispute_status)&&<div className="mt-4 flex flex-wrap gap-2"><button disabled={saving===d.id} onClick={()=>resolveDispute(d.id,'refund_buyer')} className="rounded-lg border px-3 py-2 text-xs font-semibold disabled:opacity-50">Refund buyer</button><button disabled={saving===d.id} onClick={()=>resolveDispute(d.id,'release_seller')} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Release to seller</button><button disabled={saving===d.id} onClick={()=>resolveDispute(d.id,'awaiting_evidence')} className="rounded-lg border px-3 py-2 text-xs disabled:opacity-50">Request evidence</button><button disabled={saving===d.id} onClick={()=>resolveDispute(d.id,'dismiss')} className="rounded-lg border px-3 py-2 text-xs disabled:opacity-50">Dismiss dispute</button></div>}</article>)}</div>}
  </section>
  <section className="space-y-3 rounded-xl border p-5">
   <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-lg font-bold">Financial reconciliation</h2><p className="mt-1 text-sm text-slate-500">Read-only check of the 100 most recent orders against wallet movements and seller earning records. No balances are changed automatically.</p></div><div className="flex gap-3"><div className="rounded-lg border px-4 py-2"><div className="text-xs text-slate-500">Orders checked</div><b>{reconciliation.checked_orders}</b></div><div className="rounded-lg border px-4 py-2"><div className="text-xs text-slate-500">Orders with issues</div><b>{reconciliation.issue_count}</b></div></div></div>
   {reconciliation.items.length===0?<div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">No settlement inconsistencies detected in the checked orders.</div>:<div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Order reference</th><th className="p-3 text-left">Order status</th><th className="p-3 text-left">Earning status</th><th className="p-3 text-left">Detected issues</th></tr></thead><tbody>{reconciliation.items.map(item=><tr key={item.order_id} className="border-t align-top"><td className="p-3"><div className="font-medium">{item.reference}</div><div className="text-xs text-slate-500">Order #{item.order_id}</div></td><td className="p-3">{item.status}</td><td className="p-3">{item.earning_status||'Missing'}</td><td className="p-3"><ul className="list-disc space-y-1 pl-4">{item.issues.map(issue=><li key={issue}>{issue}</li>)}</ul></td></tr>)}</tbody></table></div>}
  </section>
  <section className="space-y-3"><h2 className="text-lg font-bold">Seller earnings & category profit snapshots</h2><p className="text-sm text-slate-500">Each row records the exact gross sale, admin profit and seller net used at payment time. Later category setting changes do not rewrite these historical snapshots.</p><div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Order</th><th className="p-3 text-left">Seller</th><th className="p-3 text-left">Category</th><th className="p-3 text-left">Gross</th><th className="p-3 text-left">Admin profit</th><th className="p-3 text-left">Seller net</th><th className="p-3 text-left">Status</th></tr></thead><tbody>{earnings.data.map(e=><tr key={e.id} className="border-t"><td className="p-3">#{e.order_id}</td><td className="p-3">{e.seller?.name||e.seller?.username||'Seller'}</td><td className="p-3">{e.category?.name||'Historical / uncategorized'}</td><td className="p-3">{e.currency} {((Number(e.gross_amount_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.currency} {((Number(e.platform_profit_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.currency} {((Number(e.seller_net_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.status}</td></tr>)}</tbody></table></div></section>
  <div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Reference</th><th className="p-3 text-left">Product</th><th className="p-3 text-left">Status</th><th className="p-3 text-left">Total</th></tr></thead><tbody>{orders.data.map(o=><tr key={o.id} className="border-t"><td className="p-3">{o.reference}</td><td className="p-3">{o.product?.name}</td><td className="p-3">{o.status}</td><td className="p-3">{o.currency} {((Number(o.total_minor)||0)/100).toFixed(2)}</td></tr>)}</tbody></table></div>
 </div></>;
}
