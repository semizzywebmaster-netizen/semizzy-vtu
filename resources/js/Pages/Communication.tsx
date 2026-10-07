import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Conversation={id:number;channel:string;status:string;external_contact:string;user?:{name:string;phone:string};messages_count:number;last_message_at:string|null};
type Message={id:number;direction:string;body:string|null;status:string;created_at:string};
type Campaign={id:number;name:string;channel:string;status:string;scheduled_at:string|null;messages_count:number};

export default function Communication(){
 const [tab,setTab]=useState<'conversations'|'campaigns'|'templates'|'consent'>('conversations');
 const [conversations,setConversations]=useState<Conversation[]>([]);
 const [campaigns,setCampaigns]=useState<Campaign[]>([]);
 const [selected,setSelected]=useState<{conversation:Conversation;messages:Message[]}|null>(null);
 const [search,setSearch]=useState('');
 const [body,setBody]=useState('');
 const [loading,setLoading]=useState(false);
 const [campaign,setCampaign]=useState({name:'',channel:'whatsapp',content:'',scheduled_at:''});
 const [audience,setAudience]=useState({role:'',tier:''});
 const consentForm=useForm({channel:'whatsapp',purpose:'marketing',opted_in:true,source:'communication-center'});
 const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'';
 const load=async()=>{
  setLoading(true);
  try{
   const [a,b]=await Promise.all([
    fetch('/communication/conversations?per_page=50&search='+encodeURIComponent(search),{headers:{Accept:'application/json'}}),
    fetch('/communication/campaigns?per_page=50',{headers:{Accept:'application/json'}})
   ]);
   const aj=await a.json(), bj=await b.json();
   setConversations(aj.data||[]); setCampaigns(bj.data||[]);
  }finally{setLoading(false)}
 };
 useEffect(()=>{load()},[]);
 const open=async(c:Conversation)=>{
  const r=await fetch('/communication/conversations/'+c.id,{headers:{Accept:'application/json'}});
  const j=await r.json();setSelected({conversation:c,messages:j.messages||[]});
 };
 const reply=async()=>{
  if(!selected||!body.trim())return;
  const r=await fetch('/communication/conversations/'+selected.conversation.id+'/reply',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),Accept:'application/json'},body:JSON.stringify({body,idempotency_key:crypto.randomUUID()})});
  if(r.ok){setBody('');open(selected.conversation);load()}
 };
 const createCampaign=async()=>{
  if(!campaign.name||(!campaign.content))return;
  const r=await fetch('/communication/campaigns',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),Accept:'application/json'},body:JSON.stringify({name:campaign.name,channel:campaign.channel,content:campaign.content,audience:{role:audience.role?audience.role.split(',').map(x=>x.trim()):[],tier:audience.tier?audience.tier.split(',').map(x=>Number(x.trim())):[]},scheduled_at:campaign.scheduled_at||null})});
  if(r.ok){setCampaign({name:'',channel:'whatsapp',content:'',scheduled_at:''});load()}
 };
 const runCampaign=async(id:number)=>{
  await fetch('/communication/campaigns/'+id+'/run',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf(),Accept:'application/json'},body:JSON.stringify({limit:500})});load();
 };
 return <><Head title="Communication Center"/><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-7xl">
  <header><p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · COMMUNICATION</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Communication Center</h1><p className="mt-2 text-sm text-slate-600">WhatsApp, SMS, email and campaign communication in one place.</p></header>
  <nav className="mt-6 flex flex-wrap gap-2">{(['conversations','campaigns','templates','consent'] as const).map(x=><button key={x} onClick={()=>setTab(x)} className={'rounded-xl px-4 py-2 text-sm font-semibold '+(tab===x?'bg-indigo-600 text-white':'border bg-white text-slate-700')}>{x[0].toUpperCase()+x.slice(1)}</button>)}</nav>
  {tab==='conversations'&&<section className="mt-5 grid gap-4 lg:grid-cols-[360px_1fr]">
   <div className="rounded-2xl border bg-white p-4"><div className="flex gap-2"><input className="min-w-0 flex-1 rounded-xl border p-3" placeholder="Search user or phone" value={search} onChange={e=>setSearch(e.target.value)}/><button onClick={load} className="rounded-xl bg-slate-900 px-4 text-white">{loading?'…':'Search'}</button></div><div className="mt-4 space-y-2">{conversations.map(c=><button key={c.id} onClick={()=>open(c)} className="w-full rounded-xl border p-3 text-left hover:border-indigo-400"><div className="flex justify-between gap-2"><b>{c.user?.name||c.external_contact}</b><span className="text-xs uppercase text-slate-500">{c.channel}</span></div><p className="mt-1 text-xs text-slate-500">{c.user?.phone||c.external_contact} · {c.messages_count} messages</p></button>)}{!conversations.length&&<p className="p-6 text-center text-sm text-slate-500">No conversations found.</p>}</div></div>
   <div className="rounded-2xl border bg-white p-4">{selected?<><div className="border-b pb-4"><b className="text-lg">{selected.conversation.user?.name||selected.conversation.external_contact}</b><p className="text-sm text-slate-500">{selected.conversation.channel} · {selected.conversation.external_contact}</p></div><div className="max-h-[55vh] space-y-3 overflow-y-auto py-4">{selected.messages.map(m=><div key={m.id} className={'max-w-[80%] rounded-2xl p-3 text-sm '+(m.direction==='outbound'?'ml-auto bg-indigo-50':'bg-slate-100')}><p>{m.body}</p><span className="mt-1 block text-[11px] text-slate-500">{m.status} · {new Date(m.created_at).toLocaleString()}</span></div>)}</div><div className="flex gap-2"><textarea className="min-h-12 flex-1 rounded-xl border p-3" placeholder="Reply..." value={body} onChange={e=>setBody(e.target.value)}/><button onClick={reply} className="rounded-xl bg-indigo-600 px-5 font-semibold text-white">Send</button></div></>:<div className="grid min-h-[50vh] place-items-center text-sm text-slate-500">Select a conversation.</div>}</div>
  </section>}
  {tab==='campaigns'&&<section className="mt-5 grid gap-4 lg:grid-cols-[420px_1fr]"><div className="rounded-2xl border bg-white p-5"><h2 className="font-extrabold">Create Campaign</h2><div className="mt-4 space-y-3"><input className="w-full rounded-xl border p-3" placeholder="Campaign name" value={campaign.name} onChange={e=>setCampaign({...campaign,name:e.target.value})}/><select className="w-full rounded-xl border p-3" value={campaign.channel} onChange={e=>setCampaign({...campaign,channel:e.target.value})}><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select><textarea className="min-h-32 w-full rounded-xl border p-3" placeholder="Message content. Use template variables when using a template." value={campaign.content} onChange={e=>setCampaign({...campaign,content:e.target.value})}/><input className="w-full rounded-xl border p-3" placeholder="Roles: USER,STAFF (optional)" value={audience.role} onChange={e=>setAudience({...audience,role:e.target.value})}/><input className="w-full rounded-xl border p-3" placeholder="Tiers: 1,2,3 (optional)" value={audience.tier} onChange={e=>setAudience({...audience,tier:e.target.value})}/><input type="datetime-local" className="w-full rounded-xl border p-3" value={campaign.scheduled_at} onChange={e=>setCampaign({...campaign,scheduled_at:e.target.value})}/><button onClick={createCampaign} className="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white">Save Campaign</button></div></div><div className="space-y-3">{campaigns.map(c=><div key={c.id} className="rounded-2xl border bg-white p-4"><div className="flex flex-wrap justify-between gap-2"><div><b>{c.name}</b><p className="text-xs text-slate-500">{c.channel} · {c.messages_count} messages · {c.scheduled_at||'Ready'}</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase">{c.status}</span></div>{['draft','scheduled'].includes(c.status)&&<button onClick={()=>runCampaign(c.id)} className="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Run now</button>}</div>)}{!campaigns.length&&<div className="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">No campaigns yet.</div>}</div></section>}
  {tab==='templates'&&<section className="mt-5 rounded-2xl border bg-white p-6"><h2 className="font-extrabold">Templates</h2><p className="mt-2 text-sm text-slate-500">Manage reusable WhatsApp, SMS and email templates from the Templates endpoint. Template management remains permission controlled.</p><a href="/communication/templates" className="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Open templates API</a></section>}
  {tab==='consent'&&<section className="mt-5 max-w-xl rounded-2xl border bg-white p-6"><h2 className="font-extrabold">My Communication Consent</h2><p className="mt-2 text-sm text-slate-500">Control marketing consent for this channel.</p><select className="mt-4 w-full rounded-xl border p-3" value={consentForm.data.channel} onChange={e=>consentForm.setData('channel',e.target.value)}><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select><label className="mt-4 flex items-center gap-3"><input type="checkbox" checked={consentForm.data.opted_in} onChange={e=>consentForm.setData('opted_in',e.target.checked)}/> Allow marketing messages</label><button onClick={()=>consentForm.post('/communication/consents')} className="mt-5 rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white">Save consent</button></section>}
 </div></main></>;
}
