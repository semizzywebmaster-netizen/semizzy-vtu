import React from 'react';
import {Head,useForm} from '@inertiajs/react';
export default function MailerSmtp({profiles,settings}:any){
 const {data,setData,post,processing}=useForm({name:'',provider:'custom',host:'',port:587,encryption:'tls',username:'',password:'',from_address:'',from_name:'',enabled:true,priority:1,weight:1});
 const submit=(e:React.FormEvent)=>{e.preventDefault();post('/admin/mailer-smtp/profiles');};
 return <div className="p-6"><Head title="Mailer SMTP"/><h1 className="text-2xl font-bold mb-2">Mailer SMTP</h1><p className="mb-6 opacity-70">Unlimited SMTP profiles with priority failover. Passwords are never returned to the browser.</p>
 <form onSubmit={submit} className="grid gap-3 max-w-3xl border rounded-2xl p-5 md:grid-cols-2">
 {['name','provider','host','username','password','from_address','from_name'].map((k)=><input key={k} className="border rounded p-2" type={k==='password'?'password':'text'} placeholder={k.replace('_',' ')} value={(data as any)[k]} onChange={e=>setData(k as any,e.target.value)}/>)}
 <input className="border rounded p-2" type="number" placeholder="port" value={data.port} onChange={e=>setData('port',Number(e.target.value))}/>
 <select className="border rounded p-2" value={data.encryption} onChange={e=>setData('encryption',e.target.value)}><option value="tls">TLS</option><option value="ssl">SSL</option><option value="null">None</option></select>
 <input className="border rounded p-2" type="number" min="1" placeholder="priority" value={data.priority} onChange={e=>setData('priority',Number(e.target.value))}/>
 <input className="border rounded p-2" type="number" min="1" placeholder="weight" value={data.weight} onChange={e=>setData('weight',Number(e.target.value))}/>
 <button disabled={processing} className="border rounded p-2 md:col-span-2">{processing?'Saving…':'Add SMTP profile'}</button></form>
 <div className="mt-8 overflow-auto"><table className="min-w-full text-sm"><thead><tr><th className="p-2 text-left">Priority</th><th className="p-2 text-left">Name</th><th className="p-2 text-left">Provider</th><th className="p-2 text-left">Host</th><th className="p-2 text-left">Status</th><th className="p-2 text-left">Health</th><th className="p-2 text-left">Failures</th></tr></thead><tbody>{profiles.map((p:any)=><tr key={p.id} className="border-t"><td className="p-2">{p.priority}</td><td className="p-2">{p.name}</td><td className="p-2">{p.provider}</td><td className="p-2">{p.host}:{p.port}</td><td className="p-2">{p.enabled?'Enabled':'Disabled'}</td><td className="p-2">{p.health_status}</td><td className="p-2">{p.failure_count}</td></tr>)}</tbody></table></div></div>;
}