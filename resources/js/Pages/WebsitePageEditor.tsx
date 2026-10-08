import React,{useMemo,useState} from 'react';
import {router} from '@inertiajs/react';
type Section={id:string,type:string,data:Record<string,any>};
type Revision={id:number,version:number,status:string,created_by?:number|null,created_at?:string|null};
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
 const [open,setOpen]=useState<string|null>(null); const [seo,setSeo]=useState({...page.seo}); const [revisions,setRevisions]=useState<Revision[]>([]); const [showRevisions,setShowRevisions]=useState(false); const [loadingRevisions,setLoadingRevisions]=useState(false);
 const save=()=>router.patch('/website-builder/sites/'+site.id+'/pages/'+page.id,{title,content:{sections},seo});
 const add=(type:string)=>{const s={id:crypto.randomUUID(),type,data:defaults(type)};setSections(x=>[...x,s]);setOpen(s.id);};
 const update=(id:string,key:string,value:any)=>setSections(x=>x.map(s=>s.id===id?{...s,data:{...s.data,[key]:value}}:s));
 const move=(i:number,d:number)=>setSections(x=>{const a=[...x],j=i+d;if(j<0||j>=a.length)return a;[a[i],a[j]]=[a[j],a[i]];return a});
 const remove=(id:string)=>setSections(x=>x.filter(s=>s.id!==id));
 const loadRevisions=()=>{setLoadingRevisions(true);fetch('/website-builder/sites/'+site.id+'/pages/'+page.id+'/revisions',{headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject()).then(x=>{setRevisions(x.revisions||[]);setShowRevisions(true)}).finally(()=>setLoadingRevisions(false));};
 const restore=(id:number)=>{if(!confirm('Restore this revision? The current draft will remain recoverable as the latest revision.'))return;router.post('/website-builder/sites/'+site.id+'/pages/'+page.id+'/revisions/'+id+'/restore',{},{onSuccess:()=>window.location.reload()});};
 return <div className="p-6 max-w-6xl mx-auto space-y-5">
  <div className="flex flex-wrap justify-between gap-3"><div><h1 className="text-2xl font-bold">{site.name}</h1><p className="text-sm opacity-70">Page: {page.title} · /{page.slug}</p></div><button className="border rounded-lg px-3 py-2" onClick={()=>router.get('/website-builder')}>Back</button></div>
  <input className="border rounded-lg px-3 py-2 w-full" value={title} onChange={e=>setTitle(e.target.value)} placeholder="Page title"/>
  <div className="rounded-xl border p-4 space-y-3"><h2 className="font-semibold">Page SEO</h2>
   <input className="border rounded-lg px-3 py-2 w-full" placeholder="SEO title" value={seo.title||''} onChange={e=>setSeo({...seo,title:e.target.value})}/>
   <textarea className="border rounded-lg px-3 py-2 w-full" placeholder="Meta description" value={seo.description||''} onChange={e=>setSeo({...seo,description:e.target.value})}/>
   <div className="grid md:grid-cols-2 gap-3"><input className="border rounded-lg px-3 py-2" placeholder="Keywords" value={seo.keywords||''} onChange={e=>setSeo({...seo,keywords:e.target.value})}/><input className="border rounded-lg px-3 py-2" placeholder="OG image URL" value={seo.og_image||''} onChange={e=>setSeo({...seo,og_image:e.target.value})}/></div>
  </div>
  <div className="rounded-xl border p-4 space-y-3"><div className="flex flex-wrap items-center justify-between gap-2"><h2 className="font-semibold">Page revisions</h2><button className="border rounded-lg px-3 py-2 text-sm" onClick={loadRevisions}>{loadingRevisions?'Loading…':'View revision history'}</button></div>{showRevisions&&<div className="space-y-2">{revisions.length?revisions.map(r=><div key={r.id} className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm"><span className="flex-1"><b>Version {r.version}</b> · {r.status} · {r.created_at?new Date(r.created_at).toLocaleString():'—'}</span><button className="border rounded px-2 py-1" onClick={()=>restore(r.id)}>Restore</button></div>):<p className="text-sm opacity-70">No saved revisions yet. Save the page to create one.</p>}</div>}</div>
  <div className="rounded-xl border p-4 space-y-3"><h2 className="font-semibold">Add section</h2><div className="flex flex-wrap gap-2">{sectionTypes.map(t=><button key={t} className="border rounded-lg px-3 py-2 text-sm" onClick={()=>add(t)}>+ {t}</button>)}</div></div>
  <div className="space-y-3">{sections.map((s,i)=><div key={s.id} className="rounded-xl border p-4">
   <div className="flex flex-wrap items-center gap-2"><button className="font-semibold flex-1 text-left" onClick={()=>setOpen(open===s.id?null:s.id)}>{s.type}</button><button className="border rounded px-2 py-1 text-xs" disabled={i===0} onClick={()=>move(i,-1)}>↑</button><button className="border rounded px-2 py-1 text-xs" disabled={i===sections.length-1} onClick={()=>move(i,1)}>↓</button><button className="border rounded px-2 py-1 text-xs text-red-600" onClick={()=>remove(s.id)}>Remove</button></div>
   {open===s.id&&<div className="mt-4 grid gap-3">{Object.entries(s.data).map(([k,v])=><label key={k} className="text-sm"><span className="block mb-1 font-medium">{k}</span>{Array.isArray(v)?<textarea className="border rounded-lg p-2 w-full min-h-24" value={v.join('\n')} onChange={e=>update(s.id,k,e.target.value.split('\n'))}/>:<textarea className="border rounded-lg p-2 w-full min-h-20" value={String(v??'')} onChange={e=>update(s.id,k,e.target.value)}/>}</label>)}</div>}
  </div>)}</div>
  <div className="flex gap-3"><button className="rounded-lg px-4 py-2 bg-black text-white" onClick={save}>Save draft</button><button className="border rounded-lg px-4 py-2" onClick={()=>router.get('/website-builder')}>Back</button></div>
 </div>
}