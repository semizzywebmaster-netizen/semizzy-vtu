import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Order = {
 id:number; reference:string; status:string; currency:string; total_minor:string; quantity:string;
 fulfillment_status?:string|null; product?:{name?:string;product_type?:string}|null; buyer?:{name?:string}|null;
 shipping_carrier?:string|null; tracking_number?:string|null; tracking_url?:string|null;
};
type Props = { orders:{data:Order[];current_page:number;last_page:number;links?:any[]} };

export default function SellerOrders({orders}:Props){
 const [busy,setBusy]=useState<number|null>(null);
 const [message,setMessage]=useState('');
 const [values,setValues]=useState<Record<number,{carrier:string;tracking:string;url:string}>>({});
 const change=(id:number,key:'carrier'|'tracking'|'url',value:string)=>{
  setValues(prev=>({...prev,[id]:{carrier:prev[id]?.carrier??'',tracking:prev[id]?.tracking??'',url:prev[id]?.url??'',[key]:value}}));
 };
 const save=async(order:Order)=>{
  const v=values[order.id]||{carrier:order.shipping_carrier||'',tracking:order.tracking_number||'',url:order.tracking_url||''};
  if(!v.carrier.trim()||!v.tracking.trim()){setMessage('Enter the carrier and tracking number.');return;}
  setBusy(order.id);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  try{
   const res=await fetch('/marketplace/orders/'+order.id+'/shipping',{method:'PATCH',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({shipping_carrier:v.carrier,tracking_number:v.tracking,tracking_url:v.url||null})});
   const data=await res.json();setMessage(data.message||'Shipping details saved');if(res.ok)window.location.reload();
  }catch{setMessage('Unable to save tracking details. Please try again.');}
  finally{setBusy(null);}
 };
 return <><Head title="Seller Orders"/><main className="mx-auto max-w-5xl space-y-5 p-4 md:p-6">
  <div><h1 className="text-2xl font-bold">Seller Orders</h1><p className="mt-1 text-sm text-slate-500">Update delivery details for physical orders. Sale proceeds remain in escrow until the buyer confirms receipt and admin authorises release.</p></div>
  {message&&<div className="rounded-lg border p-3 text-sm">{message}</div>}
  {orders.data.length===0?<div className="rounded-xl border p-6 text-sm text-slate-500">No paid orders need fulfilment right now.</div>:<div className="space-y-3">{orders.data.map(order=><article key={order.id} className="rounded-xl border p-4"><div className="flex flex-wrap justify-between gap-3"><div><div className="font-semibold">{order.product?.name||'Marketplace item'}</div><div className="mt-1 text-xs text-slate-500">{order.reference} · Buyer: {order.buyer?.name||'Buyer'}</div></div><div className="text-right"><div className="font-semibold">{order.currency} {(Number(order.total_minor||'0')/100).toFixed(2)}</div><div className="text-xs text-slate-500">{order.fulfillment_status||'unfulfilled'}</div></div></div>
  {order.product?.product_type==='physical'&&<div className="mt-4 grid gap-3 md:grid-cols-3"><label className="text-sm">Carrier<input value={values[order.id]?.carrier??order.shipping_carrier??''} onChange={e=>change(order.id,'carrier',e.target.value)} maxLength={120} className="mt-1 min-h-10 w-full rounded-lg border px-3" placeholder="Courier company"/></label><label className="text-sm">Tracking number<input value={values[order.id]?.tracking??order.tracking_number??''} onChange={e=>change(order.id,'tracking',e.target.value)} maxLength={180} className="mt-1 min-h-10 w-full rounded-lg border px-3" placeholder="Shipment tracking ID"/></label><label className="text-sm">Tracking URL (optional)<input value={values[order.id]?.url??order.tracking_url??''} onChange={e=>change(order.id,'url',e.target.value)} className="mt-1 min-h-10 w-full rounded-lg border px-3" placeholder="https://..."/></label><div className="md:col-span-3"><button disabled={busy===order.id} onClick={()=>save(order)} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{busy===order.id?'Saving…':'Save shipping details'}</button></div></div>}
  {order.product?.product_type!=='physical'&&<p className="mt-3 text-sm text-slate-500">Use the applicable digital-delivery or service-submission workflow for this order.</p>}
  </article>)}</div>}
 </main></>;
}
