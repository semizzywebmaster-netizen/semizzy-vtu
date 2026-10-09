import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Order = {
 id:number; reference:string; status:string; currency:string; total_minor:string; quantity:string;
 product?:{name?:string}|null; seller?:{name?:string}|null;
 escrow?:{escrow_status:string;buyer_confirmed_at?:string|null;released_at?:string|null}|null;
 disputes?:{id:number;reason:string;description:string;status:string;resolution_note?:string|null}[];
 shipping_carrier?:string|null; tracking_number?:string|null; tracking_url?:string|null;
};
type Props = { orders:{data:Order[]; current_page:number; last_page:number; links?:any[]} };

export default function MyOrders({orders}:Props) {
 const [busy,setBusy]=useState<number|null>(null);
 const [message,setMessage]=useState('');
 const [disputeOrder,setDisputeOrder]=useState<number|null>(null);
 const [reason,setReason]=useState('item_not_received');
 const [description,setDescription]=useState('');
 const submitDispute=async(orderId:number)=>{
  if(description.trim().length<10){setMessage('Please describe the problem in at least 10 characters.');return;}
  setBusy(orderId);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  try{
   const res=await fetch('/marketplace/orders/'+orderId+'/disputes',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({reason,description})});
   const data=await res.json();setMessage(data.message||'Dispute submitted');if(res.ok)window.location.reload();
  }catch{setMessage('Unable to submit dispute. Please try again.');}
  finally{setBusy(null);}
 };
 const confirmReceipt=async(orderId:number)=>{
  if(!window.confirm('Confirm that you have received this order? Your confirmation will notify the admin, but funds will remain in escrow until an authorised admin releases them.')) return;
  setBusy(orderId);setMessage('');
  const xs=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
  const token=xs?decodeURIComponent(xs.split('=').slice(1).join('=')):'';
  try {
   const res=await fetch('/marketplace/orders/'+orderId+'/confirm-receipt',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':token},body:JSON.stringify({})});
   const data=await res.json();
   setMessage(data.message||'Request completed');
   if(res.ok) window.location.reload();
  } catch { setMessage('Unable to confirm receipt. Please try again.'); }
  finally { setBusy(null); }
 };
 return <><Head title="My Marketplace Orders"/><main className="mx-auto max-w-5xl space-y-5 p-4 md:p-6">
  <div><h1 className="text-2xl font-bold">My Marketplace Orders</h1><p className="mt-1 text-sm text-slate-500">Track deliveries and confirm receipt. Seller funds stay in escrow until an authorised admin releases them.</p></div>
  {message&&<div className="rounded-lg border p-3 text-sm">{message}</div>}
  {orders.data.length===0?<div className="rounded-xl border p-6 text-sm text-slate-500">You have not placed any marketplace orders yet.</div>:<div className="space-y-3">{orders.data.map(order=><article key={order.id} className="rounded-xl border p-4">
   <div className="flex flex-wrap items-start justify-between gap-3"><div><div className="font-semibold">{order.product?.name||'Marketplace item'}</div><div className="mt-1 text-xs text-slate-500">{order.reference} · Seller: {order.seller?.name||'Seller'}</div></div><div className="text-right"><div className="font-semibold">{order.currency} {(Number(order.total_minor||'0')/100).toFixed(2)}</div><div className="text-xs text-slate-500">Order: {order.status}</div></div></div>
   <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3"><div><div className="text-sm font-medium">Escrow: {order.escrow?.escrow_status||'Not available'}</div><div className="text-xs text-slate-500">{order.escrow?.released_at?'Seller payout released':order.escrow?.buyer_confirmed_at?'Receipt confirmed — awaiting admin release':'Funds remain held until receipt is confirmed and admin releases'}</div></div>
   {order.status==='paid'&&order.escrow?.escrow_status==='held'&&<button disabled={busy===order.id} onClick={()=>confirmReceipt(order.id)} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{busy===order.id?'Submitting…':'Confirm receipt'}</button>}
  </div>
  </article>)}</div>}
 </main></>;
}
