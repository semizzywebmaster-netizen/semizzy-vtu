import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Category = { id:number; name:string; sale_profit_bps:number; sale_profit_fixed_minor:string; parent?:{name:string}|null };
type Earning = { id:number; order_id:number; seller?:{name?:string;username?:string}|null; category?:{name?:string}|null; gross_amount_minor?:string|null; platform_profit_minor?:string|null; seller_net_minor?:string|null; currency:string; status:string; calculation_snapshot?:{configured_percentage_bps?:number;configured_fixed_minor?:string}|null };
type Props = { products:{data:any[]}; orders:{data:any[]}; categories:Category[]; earnings:{data:Earning[]} };

export default function Index({products,orders,categories,earnings}:Props){
 const [items,setItems]=useState(categories);
 const [saving,setSaving]=useState<number|null>(null);
 const [message,setMessage]=useState('');
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
  <section className="space-y-3"><h2 className="text-lg font-bold">Seller earnings & category profit snapshots</h2><p className="text-sm text-slate-500">Each row records the exact gross sale, admin profit and seller net used at payment time. Later category setting changes do not rewrite these historical snapshots.</p><div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Order</th><th className="p-3 text-left">Seller</th><th className="p-3 text-left">Category</th><th className="p-3 text-left">Gross</th><th className="p-3 text-left">Admin profit</th><th className="p-3 text-left">Seller net</th><th className="p-3 text-left">Status</th></tr></thead><tbody>{earnings.data.map(e=><tr key={e.id} className="border-t"><td className="p-3">#{e.order_id}</td><td className="p-3">{e.seller?.name||e.seller?.username||'Seller'}</td><td className="p-3">{e.category?.name||'Historical / uncategorized'}</td><td className="p-3">{e.currency} {((Number(e.gross_amount_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.currency} {((Number(e.platform_profit_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.currency} {((Number(e.seller_net_minor??'0')||0)/100).toFixed(2)}</td><td className="p-3">{e.status}</td></tr>)}</tbody></table></div></section>
  <div className="overflow-auto rounded-xl border"><table className="w-full text-sm"><thead><tr><th className="p-3 text-left">Reference</th><th className="p-3 text-left">Product</th><th className="p-3 text-left">Status</th><th className="p-3 text-left">Total</th></tr></thead><tbody>{orders.data.map(o=><tr key={o.id} className="border-t"><td className="p-3">{o.reference}</td><td className="p-3">{o.product?.name}</td><td className="p-3">{o.status}</td><td className="p-3">{o.currency} {((Number(o.total_minor)||0)/100).toFixed(2)}</td></tr>)}</tbody></table></div>
 </div></>;
}
