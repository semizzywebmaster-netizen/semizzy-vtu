import { Head, Link } from '@inertiajs/react';

type Props = {
  transaction: { id:number; reference:string; type:string; amountMinor:string; currency:string; availableAfterMinor:string; createdAt:string|null };
  platform: { platform_name?:string; business?:{phone?:string;whatsapp?:string;email?:string;address?:string;website?:string}; assets?:{logo?:string} };
};

const money=(minor:string,currency='NGN')=>{try{const v=BigInt(minor);const major=v/100n;const cents=(v%100n).toString().padStart(2,'0');return (currency==='NGN'?'₦':currency+' ')+major.toLocaleString()+'.'+cents;}catch{return '—';}};
const label=(type:string)=>type.replace(/[_:-]+/g,' ').replace(/\b\w/g,c=>c.toUpperCase());

export default function TransactionReceipt({transaction,platform}:Props){
 const b=platform.business||{}; const a=platform.assets||{};
 return <main className="min-h-screen bg-slate-100 p-4 text-slate-900">
  <Head title={'Receipt · '+transaction.reference} />
  <div className="mx-auto max-w-xl rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
   <header className="border-b border-slate-200 pb-5">
    <div className="flex items-start gap-4">
     {a.logo ? <img src={a.logo} alt={platform.platform_name||'Platform'} className="h-14 w-14 rounded-xl object-contain ring-1 ring-slate-200"/> : <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-900 text-xs font-black text-white">ONE</div>}
     <div><h1 className="text-xl font-black">{platform.platform_name||'SEMIZZY ONE'}</h1><p className="mt-1 text-xs text-slate-500">{b.address||'Platform address not configured'}</p><p className="mt-1 text-xs text-slate-500">{[b.phone,b.whatsapp,b.email].filter(Boolean).join(' · ')}</p></div>
    </div>
   </header>
   <section className="py-6">
    <p className="text-xs font-bold uppercase tracking-wider text-slate-500">Transaction receipt</p>
    <p className="mt-2 text-3xl font-black">{money(transaction.amountMinor,transaction.currency)}</p>
    <dl className="mt-6 space-y-3 text-sm"><div className="flex justify-between gap-4"><dt className="text-slate-500">Type</dt><dd className="font-bold">{label(transaction.type)}</dd></div><div className="flex justify-between gap-4"><dt className="text-slate-500">Reference</dt><dd className="font-bold">{transaction.reference}</dd></div><div className="flex justify-between gap-4"><dt className="text-slate-500">Date</dt><dd className="font-bold">{transaction.createdAt?new Date(transaction.createdAt).toLocaleString():'—'}</dd></div><div className="flex justify-between gap-4"><dt className="text-slate-500">Balance after</dt><dd className="font-bold">{money(transaction.availableAfterMinor,transaction.currency)}</dd></div></dl>
   </section>
   <footer className="border-t border-slate-200 pt-5 text-xs text-slate-500">Keep this receipt for your records. {b.website||''}</footer>
   <div className="mt-5 flex gap-3"><button onClick={()=>window.print()} className="rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Print / Save PDF</button><Link href="/transactions" className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold">Back</Link></div>
  </div>
 </main>;
}
