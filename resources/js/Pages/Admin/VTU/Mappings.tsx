import { FormEvent, useState } from 'react';
import { router } from '@inertiajs/react';

type Provider={id:number;display_name:string;identifier:string;priority:number;enabled:boolean;paused:boolean;verification_status:string;integration_status:string};
type Service={id:number;key:string;name:string;enabled:boolean};
type Mapping={id:number;provider?:Provider;service?:Service;service_key:string;provider_service_id?:string|null;capabilities?:string[]|null;enabled:boolean};
type Page<T>={data:T[]};

export default function Mappings({mappings,providers,services}:{mappings:Page<Mapping>;providers:Provider[];services:Service[]}){
 const [provider,setProvider]=useState('');
 const [service,setService]=useState('');
 const [providerServiceId,setProviderServiceId]=useState('');
 const [capabilities,setCapabilities]=useState('transaction_initiation,transaction_status');
 const [enabled,setEnabled]=useState(false);
 const submit=(e:FormEvent)=>{e.preventDefault();router.post('/admin/vtu/mappings',{api_provider_id:Number(provider),service_id:Number(service),provider_service_id:providerServiceId||null,capabilities:capabilities.split(',').map(x=>x.trim()).filter(Boolean),enabled});};
 return <div className="p-6 space-y-6">
  <div><h1 className="text-2xl font-bold">VTU Provider Mappings</h1><p className="mt-1 text-sm text-slate-500">Provider priority is controlled by Core. Mapping capabilities restrict which operations a provider may perform.</p></div>
  <form onSubmit={submit} className="grid gap-3 rounded-xl border bg-white p-5 md:grid-cols-5">
   <select value={provider} onChange={e=>setProvider(e.target.value)} required className="rounded-lg border p-2"><option value="">Provider</option>{providers.map(p=><option key={p.id} value={p.id}>{p.display_name} · P{p.priority}</option>)}</select>
   <select value={service} onChange={e=>setService(e.target.value)} required className="rounded-lg border p-2"><option value="">Service</option>{services.map(s=><option key={s.id} value={s.id}>{s.name} ({s.key})</option>)}</select>
   <input value={providerServiceId} onChange={e=>setProviderServiceId(e.target.value)} placeholder="Provider service ID" className="rounded-lg border p-2"/>
   <input value={capabilities} onChange={e=>setCapabilities(e.target.value)} placeholder="transaction_initiation,transaction_status" className="rounded-lg border p-2"/>
   <div className="flex items-center gap-3"><label className="text-sm"><input type="checkbox" checked={enabled} onChange={e=>setEnabled(e.target.checked)} className="mr-2"/>Enable</label><button className="rounded-lg bg-slate-900 px-4 py-2 text-white">Save</button></div>
  </form>
  <div className="overflow-x-auto rounded-xl border bg-white"><table className="w-full text-left text-sm"><thead><tr className="border-b bg-slate-50"><th className="p-4">Provider</th><th className="p-4">Priority</th><th className="p-4">Service</th><th className="p-4">Provider ID</th><th className="p-4">Capabilities</th><th className="p-4">Status</th></tr></thead><tbody>{mappings.data.map(m=><tr key={m.id} className="border-b last:border-0"><td className="p-4">{m.provider?.display_name}</td><td className="p-4">P{m.provider?.priority}</td><td className="p-4">{m.service?.name ?? m.service_key}</td><td className="p-4 font-mono text-xs">{m.provider_service_id ?? '—'}</td><td className="p-4">{(m.capabilities??[]).join(', ') || 'Provider defaults'}</td><td className="p-4">{m.enabled?'Enabled':'Disabled'}</td></tr>)}</tbody></table>{!mappings.data.length&&<div className="p-8 text-center text-sm text-slate-500">No VTU provider mappings configured.</div>}</div>
 </div>;
}
