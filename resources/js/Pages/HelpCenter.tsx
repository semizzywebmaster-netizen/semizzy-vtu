import CoreMobileNav from '../Components/CoreMobileNav';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type Article = { id:number; type:string; title:string; slug:string; excerpt:string|null; content:string; category:string|null; contextKey:string|null; tags:string[]|null; views:number };
type Props = { query:string; context:string|null; articles:Article[]; categories:string[] };

export default function HelpCenter({query, context, articles}:Props) {
  const [q,setQ]=useState(query);
  const [question,setQuestion]=useState('');
  const [answer,setAnswer]=useState<{answer:string;sources:{id:number;title:string;slug:string}[]}|null>(null);
  const search=()=>router.get('/help',{q,context:context??undefined},{preserveState:true,preserveScroll:true});
  const ask=async()=>{ if(!question.trim()) return; const res=await fetch('/help/assistant',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')??''},body:JSON.stringify({question,context})}); if(res.ok) setAnswer(await res.json()); };
  return <><Head title="Help Center"/><main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8"><div className="mx-auto max-w-5xl">
    <div className="rounded-3xl bg-slate-900 p-6 text-white"><p className="text-xs font-semibold uppercase tracking-wider text-indigo-300">SEMIZZY ONE</p><h1 className="mt-2 text-3xl font-extrabold">Help & Support Center</h1><p className="mt-2 text-sm text-slate-300">FAQs, guides, tutorials, contextual help and customer support in one place.</p>
      <div className="mt-5 flex gap-2"><input value={q} onChange={e=>setQ(e.target.value)} onKeyDown={e=>e.key==='Enter'&&search()} placeholder="Search help, FAQs and guides…" className="min-w-0 flex-1 rounded-xl bg-white p-3 text-slate-900"/><button onClick={search} className="rounded-xl bg-indigo-500 px-5 font-bold">Search</button></div>
    </div>
    <section className="mt-6 rounded-2xl border border-indigo-100 bg-white p-5"><h2 className="font-bold">AI Help Assistant</h2><p className="mt-1 text-sm text-slate-600">Ask a question. The assistant uses published Help Center knowledge and tracks unanswered questions.</p><div className="mt-3 flex gap-2"><input value={question} onChange={e=>setQuestion(e.target.value)} placeholder="How do I…?" className="min-w-0 flex-1 rounded-xl border p-3"/><button onClick={ask} className="rounded-xl bg-slate-900 px-4 font-semibold text-white">Ask</button></div>{answer&&<div className="mt-4 rounded-xl bg-slate-50 p-4"><p className="text-sm text-slate-800">{answer.answer}</p>{answer.sources.length>0&&<div className="mt-3 space-y-2">{answer.sources.map(s=><a key={s.id} href={'/help/articles/'+s.slug} className="block text-sm font-semibold text-indigo-700">{s.title}</a>)}</div>}</div>}</section>
    <section className="mt-8"><div className="flex items-center justify-between"><h2 className="text-xl font-bold">Resources</h2><span className="text-sm text-slate-500">{articles.length} result{articles.length===1?'':'s'}</span></div><div className="mt-3 grid gap-4 md:grid-cols-2">{articles.map(a=><a key={a.id} href={'/help/articles/'+a.slug} className="rounded-2xl border bg-white p-5 hover:border-indigo-300"><div className="flex gap-2"><span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold uppercase">{a.type}</span>{a.category&&<span className="text-xs text-slate-500">{a.category}</span>}</div><h3 className="mt-3 font-bold">{a.title}</h3><p className="mt-2 text-sm text-slate-600">{a.excerpt}</p></a>)}</div></section>
    <section className="mt-8"><h2 className="text-xl font-bold">Need human help?</h2><p className="mt-1 text-sm text-slate-600">Customer support is available through tickets.</p><a href="/support" className="mt-3 inline-block rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white">Open support ticket</a></section>
  </div><CoreMobileNav /></main></>;
}