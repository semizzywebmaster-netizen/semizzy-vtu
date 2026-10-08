import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Conversions({ requests }: { requests?: any }) {
  const [busy,setBusy]=useState(false); const [message,setMessage]=useState('');
  const [account,setAccount]=useState(''); const [selected,setSelected]=useState<number|null>(null);
  const csrf=()=>((document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement|null)?.content||'');
  const act=async(path:string,body:Record<string,string>)=>{
    setBusy(true); setMessage('');
    try { const res=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(body)}); const json=await res.json(); if(!res.ok) throw new Error(json.message||'Action failed.'); setMessage('Updated '+(json.data?.reference||'request')); location.reload(); }
    catch(e){setMessage(e instanceof Error?e.message:'Action failed.');} finally{setBusy(false);}
  };
  const rows=requests?.data||[];
  return <main className="min-h-screen bg-slate-50 p-6"><Head title="VTU Conversions" /><div className="mx-auto max-w-7xl">
    <h1 className="text-3xl font-black">Manual VTU Conversions</h1><p className="mt-2 text-sm text-slate-600">Verify the incoming airtime/data before any wallet credit. No provider API is required.</p>
    {message&&<p className="mt-3 rounded-xl bg-white p-3 text-sm font-semibold ring-1 ring-slate-200">{message}</p>}
    <div className="mt-6 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200"><table className="min-w-full text-sm"><thead><tr className="border-b text-left"><th className="p-3">Reference</th><th className="p-3">User</th><th className="p-3">Type</th><th className="p-3">Source</th><th className="p-3">Target</th><th className="p-3">Status</th><th className="p-3">Action</th></tr></thead><tbody>{rows.map((r:any)=><tr key={r.id} className="border-b"><td className="p-3 font-semibold">{r.reference}</td><td className="p-3">{r.user?.name||r.user?.username||r.user_id}</td><td className="p-3">{r.conversion_type}</td><td className="p-3">{r.source_amount_minor}</td><td className="p-3">{r.target_amount_minor}</td><td className="p-3">{r.status}</td><td className="p-3">{r.status==='pending'&&<button disabled={busy} onClick={()=>{setSelected(r.id); setAccount('');}} className="rounded-lg bg-slate-900 px-3 py-2 text-white">Verify</button>}{r.status==='verified'&&<button disabled={busy} onClick={()=>act('/admin/vtu/conversions/'+r.id+'/approve',{})} className="rounded-lg bg-emerald-600 px-3 py-2 text-white">Approve & Credit</button>}</td></tr>)}</tbody></table></div>
    {selected&&<div className="fixed inset-0 grid place-items-center bg-black/40 p-4"><div className="w-full max-w-md rounded-2xl bg-white p-5"><h2 className="text-xl font-black">Verify receiving account</h2><input value={account} onChange={e=>setAccount(e.target.value)} placeholder="Receiving number/account" className="mt-4 w-full rounded-xl border p-3"/><div className="mt-4 flex gap-2"><button onClick={()=>setSelected(null)} className="flex-1 rounded-xl border p-3">Cancel</button><button disabled={busy||!account} onClick={async()=>{await act('/admin/vtu/conversions/'+selected+'/verify',{receiving_account:account});setSelected(null)}} className="flex-1 rounded-xl bg-slate-950 p-3 font-bold text-white">Verify</button></div></div></div>}
  </div></main>;
}
