import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Feature={key:string;name:string;category:string;description:string;enabled:boolean;source:string;dependencies:string[]};
type Setting={key:string;name:string;category:string;description:string;type:string;value:any;default:any;is_secret:boolean;editable:boolean;options:any;dependencies:string[]};
type Props={tiers:any[];features:Feature[];settings?:Setting[]};
type SaveState='idle'|'dirty'|'saving'|'saved'|'failed'|'invalid';

export default function PlatformControls({tiers,features,settings=[]}:Props){
 const editableSettings=(settings||[]).filter(s=>s.editable);
 const initialSettings=Object.fromEntries(editableSettings.filter(s=>!s.is_secret).map(s=>[s.key,s.type==='json'?JSON.stringify(s.value??s.default??{},null,2):s.value??s.default??'']));
 const form=useForm({features:features.map(f=>({key:f.key,enabled:f.enabled})),tiers:Object.fromEntries((tiers||[]).map((t:any)=>[t.id,{daily_limit_minor:t.daily_limit_minor??'',balance_limit_minor:t.balance_limit_minor??''}])),settings:initialSettings});
 const [saveState,setSaveState]=useState<Record<string,SaveState>>({});
 const timers=useRef<Record<string,ReturnType<typeof setTimeout>>>({});
 const requestVersion=useRef<Record<string,number>>({});

 useEffect(()=>()=>Object.values(timers.current).forEach(clearTimeout),[]);

 const toggle=(key:string,enabled:boolean)=>form.setData('features',form.data.features.map(f=>f.key===key?{...f,enabled}:f));

 const serialize=(s:Setting,value:any)=>{
   if(s.type==='json'){
     try{return JSON.parse(String(value??''));}catch{return null;}
   }
   if(s.type==='integer'||s.type==='currency'){
     if(value===''||value===null) return value;
     const n=Number(value);
     return Number.isSafeInteger(n)?n:value;
   }
   return value;
 };

 const saveSetting=async(s:Setting)=>{
   if(s.is_secret) return;
   const raw=form.data.settings[s.key];
   const value=serialize(s,raw);
   if(s.type==='json' && value===null){
     setSaveState(x=>({...x,[s.key]:'invalid'}));
     return;
   }
   const version=(requestVersion.current[s.key]??0)+1;
   requestVersion.current[s.key]=version;
   setSaveState(x=>({...x,[s.key]:'saving'}));
   try{
     const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'';
     const response=await fetch('/admin/settings/registry/'+encodeURIComponent(s.key),{
       method:'PATCH',
       credentials:'same-origin',
       headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'},
       body:JSON.stringify({value})
     });
     const payload=await response.json().catch(()=>({}));
     if(!response.ok) throw new Error(payload.message||'Setting could not be saved.');
     if(requestVersion.current[s.key]===version){
       setSaveState(x=>({...x,[s.key]:'saved'}));
     }
   }catch{
     if(requestVersion.current[s.key]===version) setSaveState(x=>({...x,[s.key]:'failed'}));
   }
 };

 const scheduleAutoSave=(s:Setting)=>{
   if(s.is_secret) return;
   if(timers.current[s.key]) clearTimeout(timers.current[s.key]);
   setSaveState(x=>({...x,[s.key]:'dirty'}));
   timers.current[s.key]=setTimeout(()=>void saveSetting(s),700);
 };

 const setValue=(s:Setting,value:any)=>{
   form.setData('settings',{...form.data.settings,[s.key]:value});
   scheduleAutoSave(s);
 };

 const submit=(e:React.FormEvent)=>{
   e.preventDefault();
   Object.values(timers.current).forEach(clearTimeout);
   form.transform(data=>({...data,settings:Object.fromEntries(editableSettings.filter(s=>!s.is_secret).map(s=>[s.key,serialize(s,data.settings[s.key])]))})).put('/admin/platform-controls',{
     preserveScroll:true,
     onSuccess:()=>setSaveState(x=>Object.fromEntries(Object.keys(x).map(k=>[k,'saved'])) as Record<string,SaveState>),
     onError:()=>setSaveState(x=>Object.fromEntries(Object.keys(x).map(k=>[k,'failed'])) as Record<string,SaveState>),
   });
 };

 const grouped=features.reduce((a,f)=>(a[f.category]??=[]).push(f)&&a,{} as Record<string,Feature[]>);
 const status=(key:string)=>{
   const s=saveState[key];
   if(s==='saving') return <span className="text-xs font-semibold text-indigo-600">Saving…</span>;
   if(s==='saved') return <span className="text-xs font-semibold text-emerald-600">Saved ✓</span>;
   if(s==='failed') return <span className="text-xs font-semibold text-red-600">Failed · Retry</span>;
   if(s==='invalid') return <span className="text-xs font-semibold text-amber-600">Invalid</span>;
   if(s==='dirty') return <span className="text-xs font-semibold text-amber-600">Pending auto-save…</span>;
   return null;
 };

 return <><Head title="Global Feature Control"/><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-7xl">
  <header><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">SEMIZZY ONE · GLOBAL CONTROL CENTER</p><h1 className="mt-1 text-3xl font-black">Feature & Settings Control</h1><p className="mt-2 max-w-3xl text-sm text-slate-600">Core and addon capabilities are controlled centrally. Editable settings auto-save after a short pause, while individual Save and Save All controls remain available.</p></header>
  <form onSubmit={submit} className="mt-6 space-y-6">
   {Object.entries(grouped).map(([category,list])=><section key={category} className="rounded-3xl border bg-white p-5 shadow-sm"><div><h2 className="text-xl font-black">{category}</h2><p className="text-xs text-slate-500">{list.length} registered feature{list.length===1?'':'s'}</p></div>
    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{list.map(f=><label key={f.key} className="flex cursor-pointer items-start justify-between gap-4 rounded-2xl border p-4"><span><span className="block font-bold">{f.name}</span><span className="mt-1 block text-xs text-slate-500">{f.description}</span><span className="mt-2 block font-mono text-[10px] text-slate-400">{f.source} · {f.key}</span>{f.dependencies?.length>0&&<span className="mt-1 block text-[10px] text-amber-600">Requires: {f.dependencies.join(', ')}</span>}</span><input type="checkbox" className="mt-1 h-5 w-5" checked={!!form.data.features.find(x=>x.key===f.key)?.enabled} onChange={e=>toggle(f.key,e.target.checked)}/></label>)}</div>
   </section>)}
   <section className="rounded-3xl border bg-white p-5 shadow-sm"><h2 className="text-xl font-black">Tier Limits</h2><p className="mt-1 text-sm text-slate-500">Financial values are stored in minor units; NGN uses kobo.</p><div className="mt-4 grid gap-4 md:grid-cols-2">{(tiers||[]).map((t:any)=><div key={t.id} className="rounded-2xl border p-4"><div className="flex items-center justify-between"><h3 className="font-black">{t.name}</h3><span className="text-xs text-slate-500">Tier {t.id}</span></div><label className="mt-4 block text-sm font-bold">Daily transaction limit (kobo)<input inputMode="numeric" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.tiers[t.id]?.daily_limit_minor??''} onChange={e=>form.setData('tiers',{...form.data.tiers,[t.id]:{...form.data.tiers[t.id],daily_limit_minor:e.target.value}})} placeholder="0 = unlimited"/></label><label className="mt-3 block text-sm font-bold">Maximum wallet balance (kobo)<input inputMode="numeric" className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.tiers[t.id]?.balance_limit_minor??''} onChange={e=>form.setData('tiers',{...form.data.tiers,[t.id]:{...form.data.tiers[t.id],balance_limit_minor:e.target.value}})} placeholder="Leave blank if unlimited"/></label></div>)}</div></section>
   <section className="rounded-3xl border bg-white p-5 shadow-sm"><div className="flex flex-wrap items-end justify-between gap-3"><div><h2 className="text-xl font-black">Settings Registry</h2><p className="mt-1 text-sm text-slate-500">Auto-save is enabled for every editable non-secret setting. Each box also has an explicit Save button.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Auto-save · 700ms</span></div>
    <div className="mt-4 grid gap-4 md:grid-cols-2">{editableSettings.map(s=>{const value=form.data.settings[s.key]??'';return <div key={s.key} className="rounded-2xl border p-4"><div className="flex items-start justify-between gap-3"><div><div className="font-bold">{s.name}</div><div className="mt-1 text-xs text-slate-500">{s.description}</div><div className="mt-2 font-mono text-[10px] text-slate-400">{s.category} · {s.key} · {s.type}</div></div>{status(s.key)}</div>
      {s.is_secret?<div className="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">Secret setting — value is intentionally masked and must be managed through its secure workflow.</div>:s.type==='boolean'?<label className="mt-3 flex items-center gap-2 text-sm font-semibold"><input type="checkbox" className="h-5 w-5" checked={!!value} onChange={e=>setValue(s,e.target.checked)}/> Enabled</label>:s.type==='select'?<select className="mt-3 w-full rounded-xl border p-3" value={value} onChange={e=>setValue(s,e.target.value)}>{Object.entries(s.options||{}).map(([k,v])=><option key={k} value={k}>{String(v)}</option>)}</select>:s.type==='json'?<textarea className="mt-3 min-h-28 w-full rounded-xl border p-3 font-mono text-xs" value={value} onChange={e=>setValue(s,e.target.value)} onBlur={()=>{try{setValue(s,JSON.stringify(JSON.parse(value),null,2))}catch{setSaveState(x=>({...x,[s.key]:'invalid'}))}}}/>:<input className="mt-3 w-full rounded-xl border p-3" inputMode={s.type==='integer'||s.type==='currency'?'numeric':undefined} value={value} onChange={e=>setValue(s,e.target.value)}/>}
      {!s.is_secret&&<div className="mt-3 flex items-center justify-between gap-3"><span className="text-xs text-slate-400">Changes save automatically when valid.</span><button type="button" onClick={()=>{if(timers.current[s.key]) clearTimeout(timers.current[s.key]);void saveSetting(s)}} disabled={saveState[s.key]==='saving'} className="rounded-xl border px-4 py-2 text-sm font-bold disabled:opacity-50">Save</button></div>}
     </div>})}</div>
   </section>
   <div className="flex flex-wrap gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white disabled:opacity-50">{form.processing?'Publishing…':'Save All Changes'}</button><a href="/admin/settings" className="rounded-xl border bg-white px-5 py-3 font-bold">System settings</a></div>
  </form>
 </div></main></>;
}
