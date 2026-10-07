import React from 'react';
import {Head, router} from '@inertiajs/react';
type Prize={id:number;name:string;prize_type:string;amount:string;currency:string;weight:number};
type Campaign={id:number;name:string;daily_play_limit:number;prizes:Prize[]};
export default function Index({campaigns}:{campaigns:Campaign[]}){
 const [processing,setProcessing]=React.useState(false);
 const spin=(id:number)=>{setProcessing(true);router.post('/spin-to-win/'+id+'/spin',{operation_key:crypto.randomUUID()},{preserveScroll:true,onFinish:()=>setProcessing(false)});};
 return <div className="min-h-screen p-6"><Head title="Spin to Win"/><h1 className="text-2xl font-bold mb-6">Spin to Win</h1>
 {campaigns.length===0?<p>No active Spin to Win campaign is available.</p>:<div className="grid gap-5 md:grid-cols-2">{campaigns.map(c=><section key={c.id} className="rounded-2xl border p-5"><h2 className="text-xl font-semibold">{c.name}</h2><p className="text-sm opacity-70">Daily spins: {c.daily_play_limit}</p><div className="my-4 grid gap-2">{c.prizes.map(p=><div key={p.id} className="flex justify-between rounded-lg bg-black/5 p-3"><span>{p.name}</span><span>{p.prize_type==='wallet'?p.currency+' '+p.amount:p.prize_type}</span></div>)}</div><button disabled={processing} onClick={()=>spin(c.id)} className="rounded-xl px-5 py-3 font-semibold border">{processing?'Spinning…':'SPIN NOW'}</button></section>)}</div>}</div>;
}