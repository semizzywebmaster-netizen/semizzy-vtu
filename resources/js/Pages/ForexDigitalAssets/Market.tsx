import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';

type Instrument={id:number;category:string;symbol:string;base_code:string;quote_code?:string|null;name:string;instrument_type:string};
type Quote={id:number;bid?:string|null;ask?:string|null;mid?:string|null;open?:string|null;high?:string|null;low?:string|null;close?:string|null;volume?:string|null;observed_at?:string|null;expires_at?:string|null;provider?:{name?:string}};

export default function Market({instruments=[]}:{instruments?:Instrument[]}) {
 const [selected,setSelected]=useState<number|null>(instruments[0]?.id??null);
 const [quotes,setQuotes]=useState<Quote[]>([]);
 const [loading,setLoading]=useState(false);
 const instrument=instruments.find(i=>i.id===selected);
 useEffect(()=>{if(!selected){setQuotes([]);return;} setLoading(true); fetch('/api/v1/forex-digital-assets/instruments/'+selected+'/quotes',{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject()).then(d=>setQuotes(d.data??[])).catch(()=>setQuotes([])).finally(()=>setLoading(false));},[selected]);
 return <><Head title="Forex & Digital Assets"/><main className="space-y-6 p-6">
  <header><h1 className="text-2xl font-bold">Forex &amp; Digital Assets</h1><p className="mt-1 text-sm opacity-70">Verified market instruments and fresh provider-sourced quotes. Trading and exchange are unavailable until approved execution providers are configured.</p></header>
  {instruments.length===0?<section className="rounded-xl border p-6"><p className="opacity-70">No verified market instruments are currently published.</p></section>:<>
   <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{instruments.map(i=><button type="button" key={i.id} onClick={()=>setSelected(i.id)} className={'rounded-xl border p-4 text-left '+(selected===i.id?'ring-2':'')}><div className="text-xs uppercase opacity-60">{i.category.replace('_',' ')}</div><div className="mt-1 text-lg font-semibold">{i.symbol}</div><div className="text-sm opacity-70">{i.name}</div></button>)}</section>
   {instrument&&<section className="rounded-xl border p-5"><div className="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h2 className="text-xl font-semibold">{instrument.symbol}</h2><p className="text-sm opacity-60">{instrument.base_code}{instrument.quote_code?' / '+instrument.quote_code:''} · {instrument.instrument_type}</p></div><span className="rounded-full border px-3 py-1 text-xs">{loading?'Refreshing…':quotes.length?'Fresh quote':'No fresh quote'}</span></div>
    {quotes.length===0?<p className="opacity-60">No fresh verified-provider quote is available.</p>:<div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="py-2">Provider</th><th>Bid</th><th>Ask</th><th>Mid</th><th>Observed</th></tr></thead><tbody>{quotes.map(q=><tr key={q.id} className="border-b last:border-0"><td className="py-3">{q.provider?.name??'Verified provider'}</td><td>{q.bid??'—'}</td><td>{q.ask??'—'}</td><td>{q.mid??q.close??'—'}</td><td>{q.observed_at?new Date(q.observed_at).toLocaleString():'—'}</td></tr>)}</tbody></table></div>}
   </section>}
  </>}
 </main></>;
}
