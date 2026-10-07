import {Head,router} from '@inertiajs/react';
import {FormEvent,useState} from 'react';
type Product={id:number;name:string;exam_body:string;price_minor:number;currency:string};
export default function Results({products=[]}:{products?:Product[]}){
 const [productId,setProductId]=useState<number|string>(products[0]?.id??''); const [identifier,setIdentifier]=useState(''); const [candidateName,setCandidateName]=useState(''); const [message,setMessage]=useState(''); const [busy,setBusy]=useState(false);
 const submit=(e:FormEvent)=>{e.preventDefault(); if(!productId||!identifier.trim()){setMessage('Select an exam product and enter the candidate/examination number.');return;} setBusy(true); router.post('/exams/results/check',{product_id:Number(productId),candidate_identifier:identifier.trim(),candidate_name:candidateName.trim()||undefined,idempotency_key:crypto.randomUUID()},{preserveState:true,onSuccess:()=>setMessage('Result request submitted. Check your transaction history for the provider result.'),onError:()=>setMessage('Unable to process the result check.'),onFinish:()=>setBusy(false)});};
 return <><Head title="Exams & Results"/><main className="space-y-6 p-6"><header><h1 className="text-2xl font-bold">Exams &amp; Result Checking</h1><p className="text-sm opacity-70">Check examination results securely using your wallet.</p></header><form onSubmit={submit} className="max-w-xl space-y-4 rounded-xl border p-5">
 <select value={productId} onChange={e=>setProductId(e.target.value)} className="w-full rounded-lg border p-3">{products.map(p=><option key={p.id} value={p.id}>{p.exam_body} — {p.name} ({p.currency} {(p.price_minor/100).toFixed(2)})</option>)}</select>
 <input value={identifier} onChange={e=>setIdentifier(e.target.value)} placeholder="Candidate / examination number" className="w-full rounded-lg border p-3"/>
 <input value={candidateName} onChange={e=>setCandidateName(e.target.value)} placeholder="Candidate name (optional)" className="w-full rounded-lg border p-3"/>
 <button disabled={busy} className="rounded-lg bg-black px-5 py-3 text-white disabled:opacity-50">{busy?'Processing…':'Check Result'}</button>{message&&<p className="text-sm">{message}</p>}
 </form></main></>;
}
