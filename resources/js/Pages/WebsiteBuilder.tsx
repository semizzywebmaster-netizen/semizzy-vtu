import React,{useState} from 'react';
import {router} from '@inertiajs/react';

type Site={id:number,name:string,slug:string,status:string,template_key:string,pages?:Array<{id:number,title:string,slug:string}>,domains?:Array<{id:number,domain:string,status:string}>};
export default function WebsiteBuilder({sites=[]}:{sites:Site[]}) {
 const [name,setName]=useState(''); const [template,setTemplate]=useState('modern-corporate');
 const create=()=>{if(!name.trim())return;router.post('/website-builder',{name,template_key:template},{onSuccess:()=>setName('')});};
 return <div className="p-6 space-y-6">
  <div><h1 className="text-2xl font-bold">Website Builder</h1><p className="text-sm opacity-70">Create and publish professional websites from your SEMIZZY ONE account.</p></div>
  <div className="rounded-xl border p-4 space-y-3">
   <h2 className="font-semibold">Create website</h2>
   <div className="flex flex-col md:flex-row gap-3">
    <input className="border rounded-lg px-3 py-2 flex-1" value={name} onChange={e=>setName(e.target.value)} placeholder="Website name"/>
    <select className="border rounded-lg px-3 py-2" value={template} onChange={e=>setTemplate(e.target.value)}>
     {['modern-corporate','clean-saas','opay-inspired','palmpay-inspired','luxury-executive','sky-enterprise','forest-growth','crimson-modern','sunset-commerce','slate-professional'].map(x=><option key={x}>{x}</option>)}
    </select>
    <button className="rounded-lg px-4 py-2 bg-black text-white" onClick={create}>Create website</button>
   </div>
  </div>
  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
   {sites.map(site=><div key={site.id} className="rounded-xl border p-4 space-y-3">
    <div className="flex justify-between gap-3"><div><h3 className="font-semibold">{site.name}</h3><p className="text-xs opacity-60">/{site.slug}</p></div><span className="text-xs uppercase">{site.status}</span></div>
    <p className="text-sm opacity-70">Template: {site.template_key}</p>
    <div className="flex flex-wrap gap-2">
      <button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>router.get('/website-builder/sites/'+site.id+'/pages/'+(site.pages?.[0]?.id??''))}>Edit</button>
      <button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>router.post('/website-builder/sites/'+site.id+'/publish')}>Publish</button>
      <button className="border rounded-lg px-3 py-2 text-sm" onClick={()=>{const d=window.prompt('Custom domain');if(d)router.post('/website-builder/sites/'+site.id+'/domains',{domain:d});}}>Add domain</button>
    </div>
    {site.domains?.length ? <div className="text-xs opacity-70">{site.domains.map(d=><div key={d.id}>{d.domain} — {d.status}</div>)}</div>:null}
   </div>)}
  </div>
 </div>;
}