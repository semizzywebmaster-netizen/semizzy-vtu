import React from 'react';
import { Head, useForm } from '@inertiajs/react';

type Escrow={id:number;reference:string;title:string;amount_minor:string;currency:string;status:string;buyer_id:number;seller_id:number;expires_at?:string|null};
export default function Index({escrows}:{escrows:{data:Escrow[]}}){
 const {data,setData,post,processing}=useForm({seller:'',amount:'',title:'',description:'',idempotency_key:''});
 const submit=(e:React.FormEvent)=>{e.preventDefault();post('/escrow');};
 return <><Head title="Escrow"/><div className="space-y-6 p-6"><div><h1 className="text-2xl font-semibold">Escrow</h1><p className="text-sm opacity-70">Fund protected buyer-seller transactions until delivery is accepted.</p></div>
 <form onSubmit={submit} className="grid gap-3 rounded-xl border p-4 md:grid-cols-2">
  <input className="rounded border p-3" placeholder="Seller username, email or phone" value={data.seller} onChange={e=>setData('seller',e.target.value)}/>
  <input className="rounded border p-3" placeholder="Amount (₦)" value={data.amount} onChange={e=>setData('amount',e.target.value)}/>
  <input className="rounded border p-3 md:col-span-2" placeholder="Transaction title" value={data.title} onChange={e=>setData('title',e.target.value)}/>
  <textarea className="rounded border p-3 md:col-span-2" placeholder="Description / terms" value={data.description} onChange={e=>setData('description',e.target.value)}/>
  <button disabled={processing} className="rounded-lg border px-4 py-3 md:col-span-2">{processing?'Funding…':'Create & Fund Escrow'}</button>
 </form>
 <div className="overflow-x-auto rounded-xl border"><table className="w-full text-sm"><thead><tr className="border-b text-left"><th className="p-3">Reference</th><th className="p-3">Title</th><th className="p-3">Amount</th><th className="p-3">Status</th></tr></thead><tbody>{escrows?.data?.map(x=><tr key={x.id} className="border-b"><td className="p-3">{x.reference}</td><td className="p-3">{x.title}</td><td className="p-3">₦{(Number(x.amount_minor)/100).toLocaleString()}</td><td className="p-3">{x.status}</td></tr>)}</tbody></table></div>
 </div></>;
}