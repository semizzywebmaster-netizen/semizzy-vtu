import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Method={id:number;name:string;bank_name:string;account_name:string;account_number:string;instructions?:string|null;currency:string};
type Deposit={id:number;reference:string;amount_minor:string;currency:string;status:string;method?:Method|null;user_note?:string|null;admin_note?:string|null;submitted_at?:string|null;reviewed_at?:string|null};

export default function ManualDeposit({methods=[],deposits=[]}:{methods?:Method[];deposits?:Deposit[]}){
  const form=useForm<{method_id:string;amount:string;proof:File|null;user_note:string}>({method_id:methods[0]?String(methods[0].id):'',amount:'',proof:null,user_note:''});
  const selected=methods.find(m=>String(m.id)===form.data.method_id);
  const submit=(e:FormEvent)=>{e.preventDefault();form.post('/payments/manual-deposit',{forceFormData:true,preserveScroll:true,onSuccess:()=>form.reset('amount','proof','user_note')})};
  const money=(minor:string)=>{try{const n=BigInt(minor);return '₦'+(n/100n).toLocaleString()+'.'+(n%100n).toString().padStart(2,'0')}catch{return '—'}};
  return <><Head title="Manual Bank Deposit"/><main className="min-h-screen bg-slate-50 p-4 text-slate-900 sm:p-8"><div className="mx-auto max-w-5xl">
    <header><p className="text-xs font-black uppercase tracking-wider text-indigo-600">SEMIZZY ONE · NGN FUNDING</p><h1 className="mt-1 text-2xl font-black">Manual Bank Deposit</h1><p className="mt-2 text-sm text-slate-500">Transfer to a configured SEMIZZY ONE account, upload your proof, then wait for admin verification. Your wallet is not credited until approval.</p></header>
    {methods.length===0?<section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">Manual deposit is temporarily unavailable because no active bank deposit method is configured.</section>:
    <section className="mt-6 grid gap-5 lg:grid-cols-2">
      <article className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><h2 className="font-black">Transfer details</h2>
        {selected&&<div className="mt-4 rounded-2xl bg-slate-900 p-5 text-white"><p className="text-xs font-bold uppercase text-slate-400">{selected.name}</p><p className="mt-3 text-sm text-slate-300">{selected.bank_name}</p><p className="mt-1 text-xl font-black tracking-wide">{selected.account_number}</p><p className="mt-1 text-sm font-semibold">{selected.account_name}</p>{selected.instructions&&<p className="mt-4 whitespace-pre-wrap border-t border-white/10 pt-4 text-xs leading-5 text-slate-300">{selected.instructions}</p>}</div>}
        <label className="mt-4 block text-sm font-bold">Deposit method<select className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.method_id} onChange={e=>form.setData('method_id',e.target.value)}>{methods.map(m=><option key={m.id} value={m.id}>{m.name} · {m.bank_name}</option>)}</select></label>
      </article>
      <form onSubmit={submit} className="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><h2 className="font-black">Submit payment proof</h2>
        <label className="mt-4 block text-sm font-bold">Amount (NGN)<input inputMode="decimal" className="mt-1 w-full rounded-xl border p-3 font-normal" placeholder="e.g. 5000" value={form.data.amount} onChange={e=>form.setData('amount',e.target.value)}/></label>
        <label className="mt-4 block text-sm font-bold">Proof of payment<input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" className="mt-1 w-full rounded-xl border p-3 text-sm font-normal" onChange={e=>form.setData('proof',e.target.files?.[0]||null)}/><span className="mt-1 block text-xs font-normal text-slate-500">JPG, PNG, WEBP or PDF · maximum 5 MB.</span></label>
        <label className="mt-4 block text-sm font-bold">Note (optional)<textarea rows={3} className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.user_note} onChange={e=>form.setData('user_note',e.target.value)}/></label>
        {Object.values(form.errors).map((error,i)=><p key={i} className="mt-2 text-sm font-semibold text-red-600">{error}</p>)}
        <button disabled={form.processing||methods.length===0} className="mt-5 w-full rounded-xl bg-indigo-600 px-4 py-3 font-black text-white disabled:opacity-50">{form.processing?'Submitting…':'Submit for Admin Approval'}</button>
      </form>
    </section>}
    <section className="mt-6 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><h2 className="font-black">My manual deposits</h2><div className="mt-4 overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50"><tr><th className="p-3">Reference</th><th className="p-3">Amount</th><th className="p-3">Method</th><th className="p-3">Status</th><th className="p-3">Submitted</th></tr></thead><tbody>{deposits.length===0?<tr><td colSpan={5} className="p-6 text-center text-slate-500">No manual deposits yet.</td></tr>:deposits.map(d=><tr key={d.id} className="border-t"><td className="p-3 font-mono text-xs">{d.reference}</td><td className="p-3 font-bold">{money(d.amount_minor)}</td><td className="p-3">{d.method?.name||'—'}</td><td className="p-3 font-bold uppercase">{d.status}</td><td className="p-3">{d.submitted_at?new Date(d.submitted_at).toLocaleString():'—'}</td></tr>)}</tbody></table></div></section>
  </div></main></>;
}
