import React,{useEffect,useState} from 'react';
import {Head,Link} from '@inertiajs/react';

export default function Ticket({ticket}:any){
 const [code,setCode]=useState(''),[error,setError]=useState(false);
 useEffect(()=>{fetch('/api/v1/events/tickets/'+ticket.id+'/qr',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>r.ok?r.json():Promise.reject()).then(x=>setCode(x.payload)).catch(()=>setError(true));},[ticket.id]);
 return <><Head title={'Ticket '+ticket.ticket_number}/><div className="mx-auto max-w-2xl space-y-5 p-6">
  <Link href="/events" className="underline">← Events</Link>
  <div className="rounded-2xl border p-6">
   <h1 className="text-2xl font-bold">Event Ticket</h1><div className="mt-2 text-lg">{ticket.event?.title}</div>
   <div className="mt-4 space-y-2 text-sm"><div>Ticket: <b>{ticket.ticket_number}</b></div><div>Type: {ticket.ticket_type?.name}</div><div>Status: <b>{ticket.status}</b></div>{ticket.occurrence&&<div>Occurrence: {new Date(ticket.occurrence.starts_at).toLocaleString()}</div>}</div>
   <div className="mt-6 rounded-xl border p-4"><div className="font-semibold">Secure ticket code</div>{error?<p className="mt-2 text-sm">Unable to load the secure code.</p>:<div className="mt-2 break-all rounded bg-black p-3 font-mono text-xs text-white">{code||'Loading…'}</div>}<p className="mt-2 text-xs opacity-60">This signed code is verified server-side. It is not a payment confirmation or a simulated ticket.</p></div>
  </div>
 </div></>;
}