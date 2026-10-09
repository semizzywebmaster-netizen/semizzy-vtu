import { Head } from '@inertiajs/react';
import { useState } from 'react';

type Campaign = {
 id:number; advertiser_name?:string|null; name:string; objective:string; billing_model:string;
 status:string; review_status:string; currency:string; budget_minor:string|number;
 spent_minor:string|number; bid_minor:string|number; created_at:string; admin_note?:string|null;
};
type Placement = {id:number;key:string;name:string;surface:string;format:string;description?:string|null;is_active:boolean|number};
type AdType = {id:number;key:string;name:string;is_active:boolean|number};
type Stats = {campaigns:number;pending_review:number;active_campaigns:number;impressions:number;clicks:number;spend_minor:string|number};
type Props = {campaigns:Campaign[];placements:Placement[];adTypes:AdType[];stats:Stats};

function csrf(){
 const cookie=document.cookie.split('; ').find(v=>v.startsWith('XSRF-TOKEN='));
 return cookie?decodeURIComponent(cookie.split('=').slice(1).join('=')):'';
}
async function postJson(url:string,method:string,body:unknown){
 return fetch(url,{method,credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-XSRF-TOKEN':csrf()},body:JSON.stringify(body)});
}
export default function Index({campaigns,placements,adTypes,stats}:Props){
 const [message,setMessage]=useState('');
 const [busy,setBusy]=useState<number|null>(null);
 const [placement,setPlacement]=useState({key:'',name:'',surface:'global',format:'native',description:'',is_active:false});
 const [placementBusy,setPlacementBusy]=useState(false);
 const decide=async(id:number,decision:'approve'|'reject'|'pause'|'resume')=>{
  const note=window.prompt('Add a moderation/audit note (at least 5 characters):');
  if(!note||note.trim().length<5){setMessage('A moderation note is required.');return;}
  setBusy(id);setMessage('');
  try{const res=await postJson('/admin/ads/campaigns/'+id+'/review','POST',{decision,admin_note:note});const data=await res.json();setMessage(data.message||'Decision saved');if(res.ok)window.location.reload();}
  catch{setMessage('Could not save the campaign decision.');}finally{setBusy(null);}
 };
 const addPlacement=async()=>{
  if(!placement.key.trim()||!placement.name.trim()||!placement.surface.trim()){setMessage('Enter placement key, name and surface.');return;}
  setPlacementBusy(true);setMessage('');
  try{const res=await postJson('/admin/ads/placements','POST',placement);const data=await res.json();setMessage(data.message||'Placement saved');if(res.ok)window.location.reload();}
  catch{setMessage('Could not create placement.');}finally{setPlacementBusy(false);}
 };
 const money=(v:string|number)=>'₦'+(Number(v||0)/100).toLocaleString('en-NG',{minimumFractionDigits:2,maximumFractionDigits:2});
 return <><Head title="Ads & Monetization"/><main className="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
  <header><p className="text-sm font-semibold uppercase tracking-wide text-slate-500">SEMIZZY ONE · Addon</p><h1 className="mt-1 text-2xl font-bold">Ads & Monetization</h1><p className="mt-2 max-w-3xl text-sm text-slate-500">Manage advertiser campaigns, moderation, ad placements and performance. Sponsored Marketplace placements are centrally controlled here, not inside Marketplace settings.</p><a href="/admin/ads/types" className="mt-3 inline-block text-sm underline">Manage ad types & future formats →</a></header>
  {message&&<div role="status" className="rounded-lg border p-3 text-sm">{message}</div>}
  <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
   {[['Campaigns',stats.campaigns],['Pending review',stats.pending_review],['Active campaigns',stats.active_campaigns],['Impressions',stats.impressions],['Clicks',stats.clicks],['Recorded spend',money(stats.spend_minor)]].map(([label,value])=><article key={String(label)} className="rounded-xl border p-4"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-2xl font-bold">{value}</p></article>)}
  </section>
  <section className="space-y-3 rounded-xl border p-4 md:p-5"><div><h2 className="text-lg font-bold">Campaign moderation</h2><p className="mt-1 text-sm text-slate-500">New campaigns require review. Approval leaves a campaign paused until an admin explicitly resumes it.</p></div>
   {campaigns.length===0?<p className="rounded-lg border p-4 text-sm text-slate-500">No campaigns yet. Advertiser self-service and billing checkout are planned for the next implementation step.</p>:<div className="space-y-3">{campaigns.map(c=><article key={c.id} className="rounded-lg border p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-semibold">{c.name}</h3><p className="mt-1 text-xs text-slate-500">Advertiser: {c.advertiser_name||('User #'+c.id)} · {c.objective} · {c.billing_model.toUpperCase()}</p></div><div className="flex gap-2 text-xs"><span className="rounded-full border px-2 py-1">{c.review_status}</span><span className="rounded-full border px-2 py-1">{c.status}</span></div></div><div className="mt-3 grid gap-2 text-sm sm:grid-cols-3"><p>Budget: <b>{money(c.budget_minor)}</b></p><p>Spent: <b>{money(c.spent_minor)}</b></p><p>Bid: <b>{money(c.bid_minor)}</b></p></div><div className="mt-3 flex flex-wrap gap-2">{c.review_status==='pending'&&<><button disabled={busy===c.id} onClick={()=>decide(c.id,'approve')} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Approve (paused)</button><button disabled={busy===c.id} onClick={()=>decide(c.id,'reject')} className="rounded-lg border px-3 py-2 text-xs font-semibold disabled:opacity-50">Reject</button></>}{c.status==='active'&&<button disabled={busy===c.id} onClick={()=>decide(c.id,'pause')} className="rounded-lg border px-3 py-2 text-xs font-semibold">Pause campaign</button>}{c.review_status==='approved'&&c.status!=='active'&&<button disabled={busy===c.id} onClick={()=>decide(c.id,'resume')} className="rounded-lg border px-3 py-2 text-xs font-semibold">Resume campaign</button>}</div></article>)}</div>}
  </section>
  <section className="space-y-4 rounded-xl border p-4 md:p-5"><div><h2 className="text-lg font-bold">Ad placements</h2><p className="mt-1 text-sm text-slate-500">Define approved surfaces and formats. Placements are disabled by default until reviewed and activated.</p></div>
   <div className="overflow-x-auto"><table className="w-full min-w-[640px] text-left text-sm"><thead><tr className="border-b text-slate-500"><th className="p-2">Placement</th><th className="p-2">Surface</th><th className="p-2">Format</th><th className="p-2">Status</th></tr></thead><tbody>{placements.map(p=><tr key={p.id} className="border-b last:border-0"><td className="p-2"><b>{p.name}</b><div className="text-xs text-slate-500">{p.key}</div></td><td className="p-2">{p.surface}</td><td className="p-2">{p.format}</td><td className="p-2">{Boolean(p.is_active)?'Active':'Disabled'}</td></tr>)}{placements.length===0&&<tr><td colSpan={4} className="p-4 text-slate-500">No placements configured.</td></tr>}</tbody></table></div>
   <div className="grid gap-3 md:grid-cols-2"><label className="text-sm">Unique key<input value={placement.key} onChange={e=>setPlacement({...placement,key:e.target.value})} maxLength={100} placeholder="marketplace_featured_grid" className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label><label className="text-sm">Display name<input value={placement.name} onChange={e=>setPlacement({...placement,name:e.target.value})} maxLength={160} placeholder="Marketplace featured grid" className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label><label className="text-sm">Surface<input value={placement.surface} onChange={e=>setPlacement({...placement,surface:e.target.value})} maxLength={80} placeholder="marketplace" className="mt-1 min-h-10 w-full rounded-lg border px-3"/></label><label className="text-sm">Format<select value={placement.format} onChange={e=>setPlacement({...placement,format:e.target.value})} className="mt-1 min-h-10 w-full rounded-lg border px-3">{adTypes.filter(t=>Boolean(t.is_active)).map(t=><option key={t.id} value={t.key}>{t.name}</option>)}</select></label><label className="text-sm md:col-span-2">Description<textarea value={placement.description} onChange={e=>setPlacement({...placement,description:e.target.value})} maxLength={1000} rows={2} className="mt-1 w-full rounded-lg border p-3"/></label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={placement.is_active} onChange={e=>setPlacement({...placement,is_active:e.target.checked})}/>Activate placement immediately</label></div>
   <button disabled={placementBusy} onClick={addPlacement} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{placementBusy?'Saving…':'Create placement'}</button>
  </section>
  <p className="text-xs text-slate-500">Safety note: impression/click event ingestion, billing settlement, advertiser checkout, creative upload scanning and cPanel scheduled budget enforcement must be implemented and tested before campaigns can be served or charged.</p>
 </main></>;
}
