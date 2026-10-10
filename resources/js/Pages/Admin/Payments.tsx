import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Payment = {
  id: number;
  reference: string; user_id: number; amount_minor: string; currency: string; status: string;
  provider_reference?: string | null; expires_at?: string | null; paid_at?: string | null; created_at?: string | null;
};

type Provider = {
  id:number; name:string; code:string; driver:string; base_url:string|null; capabilities:string[];
  priority:number; weight:number; enabled:boolean; paused:boolean; maintenance:boolean;
  failure_count:number; cooldown_until:string|null; last_health_check_at:string|null;
  last_success_at:string|null; last_failure_at:string|null; last_error:string|null;
  credential_keys:string[]; settings:Record<string,unknown>; webhook_url:string; adapter_registered:boolean;
};

type Props = {
  payments?: {data?:Payment[]} | Payment[];
  providers?:Provider[];
  available_drivers?:string[];
  capabilities?:string[];
};

type ProviderForm = {
  name:string; code:string; driver:string; base_url:string; priority:number; weight:number;
  capabilities:string[]; settings_json:string; credentials_json:string;
};

const blank:ProviderForm = {
  name:'', code:'', driver:'paystack', base_url:'', priority:100, weight:100,
  capabilities:['collect_payment','card_payment','bank_transfer_collection','webhook','requery'],
  settings_json:JSON.stringify({require_webhook_signature:true},null,2),
  credentials_json:''
};

const pretty=(v:unknown)=>{try{return JSON.stringify(v,null,2)}catch{return '{}'}};
const statusClass=(p:Provider)=>p.enabled&&!p.paused&&!p.maintenance
  ? 'bg-emerald-100 text-emerald-700'
  : p.maintenance ? 'bg-amber-100 text-amber-700'
  : p.paused ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600';

export default function Payments({payments,providers=[],available_drivers=[],capabilities=[]}:Props){
  const rows=Array.isArray(payments)?payments:(payments?.data??[]);
  const [editing,setEditing]=useState<number|null>(null);
  const [showForm,setShowForm]=useState(false);
  const [busy,setBusy]=useState<number|null>(null);
  const form=useForm<ProviderForm>(blank);

  const openCreate=()=>{setEditing(null);form.setData(blank);form.clearErrors();setShowForm(true)};
  const openEdit=(p:Provider)=>{
    setEditing(p.id);
    form.setData({
      name:p.name,code:p.code,driver:p.driver,base_url:p.base_url||'',priority:p.priority,weight:p.weight,
      capabilities:p.capabilities||[],settings_json:pretty(p.settings||{}),credentials_json:''
    });
    form.clearErrors();setShowForm(true);
  };
  const submit=(e:React.FormEvent)=>{
    e.preventDefault();
    let settings:Record<string,unknown>={};
    let credentials:Record<string,unknown>|undefined;
    try{
      const s=JSON.parse(form.data.settings_json||'{}');
      if(!s||typeof s!=='object'||Array.isArray(s))throw new Error('Settings must be a JSON object.');
      settings=s;
      if(form.data.credentials_json.trim()){
        const c=JSON.parse(form.data.credentials_json);
        if(!c||typeof c!=='object'||Array.isArray(c))throw new Error('Credentials must be a JSON object.');
        credentials=c;
      }
    }catch(err){form.setError('settings_json',err instanceof Error?err.message:'Invalid JSON.');return}
    const payload={...form.data,settings,credentials};
    form.transform(()=>payload);
    if(editing) form.put('/admin/payments/providers/'+editing,{preserveScroll:true,onSuccess:()=>setShowForm(false)});
    else form.post('/admin/payments/providers',{preserveScroll:true,onSuccess:()=>{setShowForm(false);form.reset()}});
  };
  const action=(id:number,state:string)=>{
    setBusy(id);
    router.post('/admin/payments/providers/'+id+'/state',{state},{preserveScroll:true,onFinish:()=>setBusy(null)});
  };
  const test=(id:number)=>{
    setBusy(id);
    router.post('/admin/payments/providers/'+id+'/test',{}, {preserveScroll:true,onFinish:()=>setBusy(null)});
  };

  return <>
    <Head title="Payments & Funding"/>
    <main className="min-h-screen bg-slate-50 p-4 md:p-8">
      <div className="mx-auto max-w-7xl">
        <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADDON</p>
            <h1 className="mt-1 text-2xl font-extrabold text-slate-900">Payments & Funding</h1>
            <p className="mt-2 text-sm text-slate-600">NGN fiat gateway control center, provider failover and wallet-funding monitoring.</p>
          </div>
          <button onClick={openCreate} className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700">+ Add Gateway Provider</button>
        </header>

        <section className="mt-6 rounded-2xl border bg-white p-5 shadow-sm">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 className="text-lg font-extrabold text-slate-900">Gateway Providers</h2><p className="text-sm text-slate-500">Unlimited provider records. Secrets stay encrypted and are never returned to the browser.</p></div>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{providers.length} configured</span>
          </div>

          {showForm&&<form onSubmit={submit} className="mt-5 rounded-2xl border-2 border-indigo-100 bg-indigo-50/40 p-5">
            <div className="flex items-center justify-between"><h3 className="font-extrabold">{editing?'Edit gateway provider':'Add gateway provider'}</h3><button type="button" onClick={()=>setShowForm(false)} className="text-sm font-bold text-slate-500">Close</button></div>
            <div className="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <label className="text-sm font-semibold">Name<input className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.name} onChange={e=>form.setData('name',e.target.value)}/></label>
              <label className="text-sm font-semibold">Code<input className="mt-1 w-full rounded-xl border p-3 font-normal" placeholder="my-gateway" value={form.data.code} onChange={e=>form.setData('code',e.target.value)}/></label>
              <label className="text-sm font-semibold">Driver<input className="mt-1 w-full rounded-xl border p-3 font-normal" list="payment-gateway-drivers" placeholder="paystack or future-driver" value={form.data.driver} onChange={e=>form.setData('driver',e.target.value)}/><datalist id="payment-gateway-drivers">{available_drivers.map(d=><option key={d} value={d}/>)}</datalist></label>
              <label className="text-sm font-semibold">Base URL<input className="mt-1 w-full rounded-xl border p-3 font-normal" placeholder="https://..." value={form.data.base_url} onChange={e=>form.setData('base_url',e.target.value)}/></label>
              <label className="text-sm font-semibold">Priority<input type="number" min="0" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.priority} onChange={e=>form.setData('priority',Number(e.target.value))}/></label>
              <label className="text-sm font-semibold">Weight<input type="number" min="1" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.weight} onChange={e=>form.setData('weight',Number(e.target.value))}/></label>
            </div>
            <div className="mt-4">
              <p className="text-sm font-bold">Capabilities</p>
              <div className="mt-2 flex flex-wrap gap-2">{capabilities.map(c=><label key={c} className="flex items-center gap-2 rounded-full border bg-white px-3 py-2 text-xs"><input type="checkbox" checked={form.data.capabilities.includes(c)} onChange={e=>form.setData('capabilities',e.target.checked?[...form.data.capabilities,c]:form.data.capabilities.filter(x=>x!==c))}/>{c}</label>)}</div>
            </div>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <label className="text-sm font-semibold">Provider settings JSON<textarea rows={8} className="mt-1 w-full rounded-xl border p-3 font-mono text-xs font-normal" value={form.data.settings_json} onChange={e=>form.setData('settings_json',e.target.value)}/></label>
              <label className="text-sm font-semibold">Credentials JSON<textarea rows={8} className="mt-1 w-full rounded-xl border p-3 font-mono text-xs font-normal" placeholder={editing?'Leave blank to keep existing encrypted credentials. Example: {"secret_key":"...","public_key":"...","webhook_secret":"..."}':'Example: {"secret_key":"...","webhook_secret":"..."}'} value={form.data.credentials_json} onChange={e=>form.setData('credentials_json',e.target.value)}/><span className="mt-1 block text-xs font-normal text-slate-500">Credentials are encrypted at rest. Existing secret values are never prefilled.</span></label>
            </div>
            {form.errors.settings_json&&<p className="mt-2 text-sm font-semibold text-red-600">{form.errors.settings_json}</p>}
            {form.errors.code&&<p className="mt-2 text-sm font-semibold text-red-600">{form.errors.code}</p>}
            <button disabled={form.processing} className="mt-4 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white">{form.processing?'Saving…':editing?'Save & disable for re-test':'Create disabled provider'}</button>
          </form>}

          <div className="mt-5 space-y-4">
            {providers.length===0&&<div className="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500">No gateway providers configured.</div>}
            {providers.map(p=><article key={p.id} className="rounded-2xl border p-4">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2"><h3 className="font-extrabold text-slate-900">{p.name}</h3><span className={'rounded-full px-2.5 py-1 text-xs font-bold '+statusClass(p)}>{p.enabled&&!p.paused&&!p.maintenance?'LIVE':p.maintenance?'MAINTENANCE':p.paused?'PAUSED':'DISABLED'}</span><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{p.driver}</span>{!p.adapter_registered&&<span className="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700">NO ADAPTER</span>}</div>
                  <p className="mt-1 font-mono text-xs text-slate-500">{p.code} · priority {p.priority} · weight {p.weight}</p>
                  <div className="mt-3 flex flex-wrap gap-1">{p.capabilities.map(c=><span key={c} className="rounded-md bg-slate-100 px-2 py-1 text-[11px]">{c}</span>)}</div>
                  <p className="mt-3 text-xs text-slate-500">Credentials configured: {p.credential_keys.length? p.credential_keys.join(', '):'none'} · failures: {p.failure_count}</p>
                  {p.last_error&&<p className="mt-2 rounded-lg bg-red-50 p-2 text-xs text-red-700">{p.last_error}</p>}
                </div>
                <div className="flex flex-wrap gap-2 lg:max-w-md lg:justify-end">
                  <button onClick={()=>test(p.id)} disabled={busy===p.id} className="rounded-lg border px-3 py-2 text-xs font-bold">Test Connection</button>
                  <button onClick={()=>openEdit(p)} className="rounded-lg border px-3 py-2 text-xs font-bold">Edit</button>
                  {!p.enabled?<button onClick={()=>action(p.id,'enable')} disabled={busy===p.id} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Enable</button>:<button onClick={()=>action(p.id,'disable')} disabled={busy===p.id} className="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white">Disable</button>}
                  {p.paused?<button onClick={()=>action(p.id,'resume')} className="rounded-lg border px-3 py-2 text-xs font-bold">Resume</button>:<button onClick={()=>action(p.id,'pause')} className="rounded-lg border px-3 py-2 text-xs font-bold">Pause</button>}
                  {!p.maintenance?<button onClick={()=>action(p.id,'maintenance_on')} className="rounded-lg border px-3 py-2 text-xs font-bold">Maintenance</button>:<button onClick={()=>action(p.id,'maintenance_off')} className="rounded-lg border px-3 py-2 text-xs font-bold">End Maintenance</button>}
                </div>
              </div>
              <div className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-xs md:grid-cols-3">
                <div><b>Webhook URL</b><div className="mt-1 break-all font-mono">{p.webhook_url}</div></div>
                <div><b>Health</b><div className="mt-1">{p.last_health_check_at?new Date(p.last_health_check_at).toLocaleString():'Not tested'}</div></div>
                <div><b>Cooldown</b><div className="mt-1">{p.cooldown_until?new Date(p.cooldown_until).toLocaleString():'None'}</div></div>
              </div>
            </article>)}
          </div>
        </section>

        <section className="mt-6 overflow-hidden rounded-2xl border bg-white shadow-sm">
          <div className="border-b p-5"><h2 className="text-lg font-extrabold">Funding Transactions</h2><p className="text-sm text-slate-500">Verified payment intents that ultimately credit the Core NGN wallet and ledger.</p></div>
          <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50"><tr><th className="p-4">Reference</th><th className="p-4">User</th><th className="p-4">Amount</th><th className="p-4">Status</th><th className="p-4">Provider ref.</th><th className="p-4">Paid</th><th className="p-4">Actions</th></tr></thead>
            <tbody>{rows.length===0?<tr><td colSpan={7} className="p-8 text-center text-slate-500">No payment intents yet.</td></tr>:rows.map(p=><tr key={p.reference} className="border-t"><td className="p-4 font-mono font-semibold">{p.reference}</td><td className="p-4">{p.user_id}</td><td className="p-4">{p.amount_minor} {p.currency}</td><td className="p-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold uppercase">{p.status}</span></td><td className="p-4 font-mono text-xs">{p.provider_reference||'—'}</td><td className="p-4">{p.paid_at?new Date(p.paid_at).toLocaleString():'—'}</td><td className="p-4"><button onClick={()=>router.post('/admin/payments/payments/'+p.id+'/requery',{}, {preserveScroll:true})} className="rounded-lg border px-3 py-2 text-xs font-bold">Requery</button></td></tr>)}</tbody>
          </table></div>
        </section>
      </div>
    </main>
  </>;
}
