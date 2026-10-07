import React,{useMemo,useState} from 'react';
import {router} from '@inertiajs/react';
type Section={id:string,type:string,data:Record<string,any>};
type Props={site:{id:number,name:string,slug:string},page:{id:number,title:string,content?:{sections?:Section[]},seo?:Record<string,any>},sectionTypes:string[]};

const defaults=(type:string)=>({
 hero:{heading:'Your headline',text:'Tell visitors what your business does.',button:'Get Started'},
 text:{heading:'About us',text:'Add your content here.'},
 image:{url:'',alt:''},
 features:{heading:'Features',items:['Feature one','Feature two','Feature three']},
 pricing:{heading:'Pricing',items:['Starter','Professional','Business']},
 services:{heading:'Our services',items:['Service one','Service two']},
 testimonials:{heading:'What customers say',items:['Great service.']},
 faq:{heading:'Frequently asked questions',items:['Question and answer']},
 cta:{heading:'Ready to get started?',button:'Contact us'},
 contact:{heading:'Contact us',text:'Phone, email and address.'},
 footer:{text:'© Your Business'}
}[type]??{});

export default function WebsitePageEditor({site,page,sectionTypes=[]}:Props){
 const [title,setTitle]=useState(page.title);
 const [sections,setSections]=useState<Section[]>(page.content?.sections??[]);
 const [open,setOpen]=useState<string|null>(null);
 const save=()=>router.patch('/website-builder/sites/'+site.id+'/pages/'+page.id,{title,content:{sections},seo:page.seo??{}});
 const add=(type:string)=>{const s={id:crypto.randomUUID(),type,data:defaults(type)};setSections(x=>[...x,s]);setOpen(s.id);};
 const update=(id:string,key:string,value:any)=>setSections(x=>x.map(s=>s.id===id?{...s,data:{...s.data,[key]:value}}:s));
 const move=(i:number,d:number)=>setSections(x=>{const a=[...x],j=i+d;if(j<0||j>=a.length)return a;[a[i],a[j]]=[a[j],a[i]];return a});
 const remove=(id:string)=>setSections(x=>x.filter(s=>s.id!==id));
 return <div className="p-6 max-w-6xl mx-auto space-y-5">
  <div className="flex flex-wrap justify-between gap-3"><div><h1 className="text-2xl font-bold">{site.name}</h1><p className="text-sm opacity-70">Page: {page.title} · /{page.slug}</p></div><button className="border rounded-lg px-3 py-2" onClick={()=>router.get('/website-builder')}>Back</button></div>
  <input className="border rounded-lg px-3 py-2 w-full" value={title} onChange={e=>setTitle(e.target.value)} placeholder="Page title"/>
  <div className="rounded-xl border p-4 space-y-3"><h2 className="font-semibold">Add section</h2><div className="flex flex-wrap gap-2">{sectionTypes.map(t=><button key={t} className="border rounded-lg px-3 py-2 text-sm" onClick={()=>add(t)}>+ {t}</button>)}</div></div>
  <div className="space-y-3">{sections.map((s,i)=><div key={s.id} className="rounded-xl border p-4">
   <div className="flex flex-wrap items-center gap-2"><button className="font-semibold flex-1 text-left" onClick={()=>setOpen(open===s.id?null:s.id)}>{s.type}</button><button className="border rounded px-2 py-1 text-xs" disabled={i===0} onClick={()=>move(i,-1)}>↑</button><button className="border rounded px-2 py-1 text-xs" disabled={i===sections.length-1} onClick={()=>move(i,1)}>↓</button><button className="border rounded px-2 py-1 text-xs text-red-600" onClick={()=>remove(s.id)}>Remove</button></div>
   {open===s.id&&<div className="mt-4 grid gap-3">{Object.entries(s.data).map(([k,v])=><label key={k} className="text-sm"><span className="block mb-1 font-medium">{k}</span>{Array.isArray(v)?<textarea className="border rounded-lg p-2 w-full min-h-24" value={v.join('\n')} onChange={e=>update(s.id,k,e.target.value.split('\n'))}/>:<textarea className="border rounded-lg p-2 w-full min-h-20" value={String(v??'')} onChange={e=>update(s.id,k,e.target.value)}/>}</label>)}</div>}
  </div>)}</div>
  <div className="flex gap-3"><button className="rounded-lg px-4 py-2 bg-black text-white" onClick={save}>Save draft</button><button className="border rounded-lg px-4 py-2" onClick={()=>router.get('/website-builder')}>Back</button></div>
 </div>
}