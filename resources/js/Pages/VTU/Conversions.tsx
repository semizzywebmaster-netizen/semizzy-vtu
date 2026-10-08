import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const TYPES: Record<string,string> = {
  airtime_to_cash:'Airtime → Cash',
  airtime_to_data:'Airtime → Data',
  data_to_cash:'Data → Cash',
  data_to_airtime:'Data → Airtime',
};

export default function Conversions({ conversionTypes=TYPES, requests }: { conversionTypes?: Record<string,string>; requests?: any }) {
  const [type,setType]=useState('airtime_to_cash');
  const [network,setNetwork]=useState('');
  const [amount,setAmount]=useState('');
  const [phone,setPhone]=useState('');
  const [proof,setProof]=useState<File|null>(null);
  const [busy,setBusy]=useState(false);
  const [message,setMessage]=useState('');
  const submit=async()=>{
    setBusy(true); setMessage('');
    try {
      const token=(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement|null)?.content||'';
      const body=new FormData();
      body.append('conversion_type',type); body.append('network',network); body.append('source_amount',amount);
      if(phone) body.append('source_phone',phone); if(proof) body.append('proof',proof);
      body.append('rate','100'); body.append('fee','0');
      const res=await fetch('/vtu/conversions',{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':token},body});
      const json=await res.json(); if(!res.ok) throw new Error(json.message||'Conversion request failed.');
      setMessage('Request submitted: '+json.data.reference); setAmount(''); setPhone(''); setProof(null);
    } catch(e) { setMessage(e instanceof Error?e.message:'Conversion request failed.'); }
    finally { setBusy(false); }
  };
  return <main className="min-h-screen bg-slate-50 pb-24"><Head title="Conversions" /><div className="mx-auto max-w-4xl px-4 py-6">
    <Link href="/vtu" className="text-sm font-semibold text-indigo-600">← Back to Services</Link>
    <h1 className="mt-4 text-3xl font-black text-slate-900">Manual Conversion</h1>
    <p className="mt-2 text-sm text-slate-600">Submit airtime/data conversion requests for operator verification. Wallet credit happens only after approval.</p>
    <div className="mt-6 grid gap-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2">
      <label className="text-sm font-semibold">Conversion type<select value={type} onChange={e=>setType(e.target.value)} className="mt-2 w-full rounded-xl border p-3">{Object.entries(conversionTypes).map(([k,v])=><option key={k} value={k}>{v}</option>)}</select></label>
      <label className="text-sm font-semibold">Network<input value={network} onChange={e=>setNetwork(e.target.value)} className="mt-2 w-full rounded-xl border p-3" placeholder="MTN, Airtel, Glo, 9mobile" /></label>
      <label className="text-sm font-semibold">Source amount<input value={amount} onChange={e=>setAmount(e.target.value)} className="mt-2 w-full rounded-xl border p-3" inputMode="decimal" /></label>
      <label className="text-sm font-semibold">Source phone<input value={phone} onChange={e=>setPhone(e.target.value)} className="mt-2 w-full rounded-xl border p-3" /></label>
      <label className="text-sm font-semibold sm:col-span-2">Proof / transfer evidence<input type="file" onChange={e=>setProof(e.target.files?.[0]??null)} className="mt-2 block w-full" accept=".jpg,.jpeg,.png,.pdf" /></label>
      <button disabled={busy} onClick={submit} className="rounded-xl bg-slate-950 px-4 py-3 font-bold text-white disabled:opacity-50 sm:col-span-2">{busy?'Submitting…':'Submit conversion request'}</button>
      {message&&<p className="text-sm font-semibold sm:col-span-2">{message}</p>}
    </div>
  </div></main>;
}
