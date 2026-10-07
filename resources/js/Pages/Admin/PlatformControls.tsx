import { Head, useForm } from '@inertiajs/react';

type Feature={key:string;name:string;category:string;description:string;enabled:boolean;source:string;dependencies:string[]};
type Props={tiers:any[];features:Feature[]};

export default function PlatformControls({tiers,features}:Props){
 const form=useForm({features:features.map(f=>({key:f.key,enabled:f.enabled})),tiers:Object.fromEntries((tiers||[]).map((t:any)=>[t.id,{daily_limit_minor:t.daily_limit_minor??'',balance_limit_minor:t.balance_limit_minor??''}]))});
 const toggle=(key:string,enabled:boolean)=>form.setData('features',form.data.features.map(f=>f.key===key?{...f,enabled}:f));
 const submit=(e:React.FormEvent)=>{e.preventDefault();form.put('/admin/platform-controls',{preserveScroll:true});};
 const grouped=features.reduce((a,f)=>(a[f.category]??=[]).push(f)&&a,{ } as Record<string,Feature[]>);
 return <><Head title="Global Feature Control"/><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-7xl">
  <header><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">SEMIZZY ONE · GLOBAL CONTROL CENTER</p><h1 className="mt-1 text-3xl font-black">Feature & Settings Control</h1><p className="mt-2 max-w-3xl text-sm text-slate-600">Enable or disable Core and addon capabilities centrally. Changes are persisted and enforced server-side without redeployment.</p></header>
  <form onSubmit={submit} className="mt-6 space-y-6">
   {Object.entries(grouped).map(([category,list])=><section key={category} className="rounded-3xl border bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><div><h2 className="text-xl font-black">{category}</h2><p className="text-xs text-slate-500">{list.length} registered feature{list.length===1?'':'s'}</p></div></div>
    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{list.map(f=><label key={f.key} className="flex cursor-pointer items-start justify-between gap-4 rounded-2xl border p-4"><span><span className="block font-bold">{f.name}</span><span className="mt-1 block text-xs text-slate-500">{f.description}</span><span className="mt-2 block font-mono text-[10px] text-slate-400">{f.source} · {f.key}</span>{f.dependencies?.length>0&&<span className="mt-1 block text-[10px] text-amber-600">Requires: {f.dependencies.join(', ')}</span>}</span><input type="checkbox" className="mt-1 h-5 w-5" checked={!!form.data.features.find(x=>x.key===f.key)?.enabled} onChange={e=>toggle(f.key,e.target.checked)}/></label>)}</div>
   </section>)}
   <section className="rounded-3xl border bg-white p-5 shadow-sm"><h2 className="text-xl font-black">Tier Limits</h2><p className="mt-1 text-sm text-slate-500">Financial values are stored in minor units; NGN uses kobo.</p><div className="mt-4 grid gap-4 md:grid-cols-2">{(tiers||[]).map((t:any)=><div key={t.id} className="rounded-2xl border p-4"><div className="flex items-center justify-between"><h3 className="font-black">{t.name}</h3><span className="text-xs text-slate-500">Tier {t.id}</span></div><label className="mt-4 block text-sm font-bold">Daily transaction limit (kobo)<input inputMode="numeric" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.tiers[t.id]?.daily_limit_minor??''} onChange={e=>form.setData('tiers',{...form.data.tiers,[t.id]:{...form.data.tiers[t.id],daily_limit_minor:e.target.value}})} placeholder="0 = unlimited"/></label><label className="mt-3 block text-sm font-bold">Maximum wallet balance (kobo)<input inputMode="numeric" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.tiers[t.id]?.balance_limit_minor??''} onChange={e=>form.setData('tiers',{...form.data.tiers,[t.id]:{...form.data.tiers[t.id],balance_limit_minor:e.target.value}})} placeholder="Leave blank if unlimited"/></label></div>)}</div></section>
   <div className="flex flex-wrap gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white disabled:opacity-50">{form.processing?'Publishing…':'Save & publish globally'}</button><a href="/admin/settings" className="rounded-xl border bg-white px-5 py-3 font-bold">System settings</a></div>
  </form>
 </div></main></>;
}
