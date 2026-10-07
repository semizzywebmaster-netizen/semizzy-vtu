import {useState} from 'react';
export default function Results({products=[]}:{products?:Array<{id:number;name:string;exam_body:string;price_minor:number;currency:string}>}){
 const [productId,setProductId]=useState(products[0]?.id??'');
 const [identifier,setIdentifier]=useState('');
 const [message,setMessage]=useState('');
 async function submit(){setMessage('Request prepared. Provider processing will be enabled in the transaction service layer.');}
 return <div className="space-y-6 p-6"><h1 className="text-2xl font-semibold">Exams &amp; Result Checking</h1><div className="rounded-xl border p-5 space-y-4"><select value={productId} onChange={e=>setProductId(e.target.value as never)} className="w-full rounded border p-3">{products.map(p=><option key={p.id} value={p.id}>{p.exam_body} — {p.name} ({p.currency} {(p.price_minor/100).toFixed(2)})</option>)}</select><input value={identifier} onChange={e=>setIdentifier(e.target.value)} placeholder="Candidate / examination number" className="w-full rounded border p-3"/><button onClick={submit} className="rounded-lg px-4 py-2">Check Result</button>{message&&<p>{message}</p>}</div></div>;
}
