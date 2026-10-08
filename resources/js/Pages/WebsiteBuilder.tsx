import React,{useState} from 'react';
import {router} from '@inertiajs/react';

type Page={id:number,title:string,slug:string,is_home:boolean,status:string,sort_order:number,content?:{sections?:Section[]}};
type Section={id:string,type:string,data:Record<string,any>};
type Site={id:number,name:string,slug:string,status:string,template_key:string,settings?:Record<string,any>,pages?:Page[],domains?:Array<{id:number,domain:string,status:string}>};

const templates=['modern-corporate','clean-saas','opay-inspired','palmpay-inspired','luxury-executive','sky-enterprise','forest-growth','crimson-modern','sunset-commerce','slate-professional'];

export default function WebsiteBuilder({sites=[]}:{sites:Site[]}) {
 const [name,setName]=useState(''); const [template,setTemplate]=useState('modern-corporate');
 const create=()=>{if(!name.trim())return;router.post('/website-builder',{name,template_key:template},{onSuccess:()=>setName('')});};
 const addPage=(site:Site)=>{const title=window.prompt('New page title');if(title?.trim())router.post('/website-builder/sites/'+site.id+'/pages',{title:title.trim()});};
 const reorder=(site:Site,pages:Page[])=>router.post('/website-builder/sites/'+site.id+'/pages/reorder',{pages:pages.map(p=>p.id)});
 const editSettings=(site:Site)=>{
  const s=site.settings||{}; const branding=s.branding||{}; const theme=s.theme||{};
  const next={branding:{
   logo_url:window.prompt('Logo URL',branding.logo_url||'')||branding.logo_url||'',
   favicon_url:window.prompt('Favicon URL',branding.favicon_url||'')||branding.favicon_url||'',
   phone:window.prompt('Business phone',branding.phone||'')||branding.phone||'',
   email:window.prompt('Business email',branding.email||'')||branding.email||'',
   whatsapp:window.prompt('WhatsApp URL',branding.whatsapp||'')||branding.whatsapp||'',
   facebook:window.prompt('Facebook URL',branding.facebook||'')||branding.facebook||'',
   instagram:window.prompt('Instagram URL',branding.instagram||'')||branding.instagram||'',
   twitter:window.prompt('X/Twitter URL',branding.twitter||'')||branding.twitter||'',
  },theme:{...theme,
   primary:window.prompt('Custom primary colour (hex)',theme.primary||'')||theme.primary||'',
   background:window.prompt('Custom background colour (hex)',theme.background||'')||theme.background||'',
   text:window.prompt('Custom text colour (hex)',theme.text||'')||theme.text||'',
   card:window.prompt('Custom card colour (hex)',theme.card||'')||theme.card||'',
  },seo:{
   title:window.prompt('Default SEO title',(s.seo||{}).title||site.name)||((s.seo||{}).title||site.name),
   description:window.prompt('Default SEO description',(s.seo||{}).description||'')||((s.seo||{}).description||''),
   keywords:window.prompt('SEO keywords',(s.seo||{}).keywords||'')||((s.seo||{}).keywords||''),
   og_image:window.prompt('Open Graph image URL',(s.seo||{}).og_image||'')||((s.seo||{}).og_image||''),
  }};
  router.patch('/website-builder/sites/'+site.id,{name:site.name,template_key:site.template_key,settings:next});
 };
 return <div className="p-6 space-y-6">
  <div><h1 className="text-2xl font-bold">Website Builder</h1><p className="text-sm opacity-70">Build professional multi-page websites with structured sections.</p></div>
  <div className="rounded-xl border p-4 space-y-3">
   <h2 className="font-semibold">Create website</h2>
   <div className="flex flex-col md:flex-row gap-3">
    <input className="border rounded-lg px-3 py-2 flex-1" value={name} onChange={e=>setName(e.target.value)} placeholder="Website name"/>
    <select className="border rounded-lg px-3 py-2" value={template} onChange={e=>setTemplate(e.target.value)}>{templates.map(x=><option key={x}>{x}</option>)}</select>
    <button className="rounded-lg px-4 py-2 bg-black text-white" onClick={create}>Create website</button>
   </div>
  </div>
  <div className="grid gap-4">
   {sites.map(site=>{const pages=[...(site.pages??[])].sort((a,b)=>a.sort_order-b.sort_order);return <div key={site.id} className="rounded-xl border p-4 space-y-4">
    <div className="flex flex-wrap justify-between gap-3"><div><h3 className="font-semibold">{site.name}</h3><p className="text-xs opacity-60">/{site.slug} · {site.template_key}</p></div><span className="text-xs uppercase">{site.status}</span></div>
    <div className="flex flex-wrap gap-2"><button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>addPage(site)}>+ Add page</button><button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>editSettings(site)}>Branding / Theme / SEO</button><button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>router.get('/website-builder/sites/'+site.id+'/preview')}>Preview</button><button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>router.post('/website-builder/sites/'+site.id+'/publish')}>Publish</button><button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>{const d=window.prompt('Custom domain');if(d)router.post('/website-builder/sites/'+site.id+'/domains',{domain:d});}}>Add domain</button></div>
    <div className="space-y-2">{pages.map((p,i)=><div key={p.id} className="flex flex-wrap items-center gap-2 rounded-lg border p-3">
      <span className="flex-1"><b>{p.title}</b> <span className="text-xs opacity-60">/{p.slug}{p.is_home?' · HOME':''}</span></span>
      <button className="border rounded px-2 py-1 text-xs" onClick={()=>router.get('/website-builder/sites/'+site.id+'/pages/'+p.id)}>Edit</button>
      {!p.is_home&&<button className="border rounded px-2 py-1 text-xs" onClick={()=>router.post('/website-builder/sites/'+site.id+'/pages/'+p.id+'/home')}>Set Home</button>}
      <button className="border rounded px-2 py-1 text-xs" disabled={i===0} onClick={()=>{const x=[...pages];[x[i-1],x[i]]=[x[i],x[i-1]];reorder(site,x)}}>↑</button>
      <button className="border rounded px-2 py-1 text-xs" disabled={i===pages.length-1} onClick={()=>{const x=[...pages];[x[i+1],x[i]]=[x[i],x[i+1]];reorder(site,x)}}>↓</button>
      {!p.is_home&&<button className="border rounded px-2 py-1 text-xs text-red-600" onClick={()=>{if(confirm('Delete this page?'))router.delete('/website-builder/sites/'+site.id+'/pages/'+p.id)}}>Delete</button>}
    </div>)}</div>
    {site.domains?.length?<div className="text-xs opacity-70">{site.domains.map(d=><div key={d.id}>{d.domain} — {d.status}</div>)}</div>:null}
   </div>})}
  </div>
 </div>;
}