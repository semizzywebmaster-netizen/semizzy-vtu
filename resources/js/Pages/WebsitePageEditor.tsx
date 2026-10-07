import React,{useState} from 'react';
import {router} from '@inertiajs/react';
type Props={site:{id:number,name:string,slug:string},page:{id:number,title:string,content?:any,seo?:any}};
export default function WebsitePageEditor({site,page}:Props){
 const [title,setTitle]=useState(page.title); const [content,setContent]=useState(JSON.stringify(page.content??{sections:[]},null,2));
 const save=()=>{try{router.patch('/website-builder/sites/'+site.id+'/pages/'+page.id,{title,content:JSON.parse(content)})}catch{alert('Content must be valid JSON.')}};
 return <div className="p-6 max-w-5xl mx-auto space-y-5"><div><h1 className="text-2xl font-bold">{site.name}</h1><p className="text-sm opacity-70">Editing: {page.title}</p></div>
 <input className="border rounded-lg px-3 py-2 w-full" value={title} onChange={e=>setTitle(e.target.value)} placeholder="Page title"/>
 <div><label className="text-sm font-medium">Page content</label><textarea className="border rounded-lg p-3 w-full min-h-[420px] font-mono text-sm" value={content} onChange={e=>setContent(e.target.value)}/></div>
 <div className="flex gap-3"><button className="rounded-lg px-4 py-2 bg-black text-white" onClick={save}>Save draft</button><button className="border rounded-lg px-4 py-2" onClick={()=>router.get('/website-builder')}>Back</button></div>
 </div>
}