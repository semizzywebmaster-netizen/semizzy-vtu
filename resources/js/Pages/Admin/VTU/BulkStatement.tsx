import { Head } from '@inertiajs/react';

type Item={id:number;sequence:number;status:string;recipient?:string|null;amount_minor?:string|number|null;product?:{name?:string|null}|null;transaction?:{reference?:string|null;provider_reference?:string|null}|null;error_message?:string|null;created_at?:string|null};
type Statement={reference:string;status:string;currency:string;created_at?:string|null;user?:{name?:string|null;email?:string|null}|null;total_items:number;successful_items:number;failed_items:number;processed_items:number;pending_items:number;total_amount_minor:string;successful_amount_minor:string;failed_amount_minor:string;items:Item[]};

const money=(minor:string|number|null|undefined,currency:string)=>{const n=Number(minor??0)/100;return new Intl.NumberFormat('en-NG',{style:'currency',currency,minimumFractionDigits:2}).format(Number.isFinite(n)?n:0)};
export default function BulkStatement({statement}:{statement:Statement}){
 const print=()=>window.print();
 return <><Head title={`Bulk Statement - ${statement.reference}`} />
 <div className="min-h-screen bg-slate-100 p-4 text-slate-900 print:bg-white md:p-8">
  <div className="mx-auto max-w-6xl rounded-2xl bg-white p-5 shadow-sm print:shadow-none">
   <div className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-start sm:justify-between">
    <div><p className="text-xs font-bold uppercase tracking-widest text-slate-500">SEMIZZY ONE · VTU & Digital Services</p><h1 className="mt-1 text-2xl font-bold">Bulk Transaction Statement</h1><p className="mt-1 font-mono text-sm">{statement.reference}</p></div>
    <button onClick={print} className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white print:hidden">Print / Save PDF</button>
   </div>
   <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div className="rounded-xl border p-3"><div className="text-xs text-slate-500">Status</div><div className="mt-1 font-bold">{statement.status}</div></div>
    <div className="rounded-xl border p-3"><div className="text-xs text-slate-500">Customer</div><div className="mt-1 font-semibold">{statement.user?.name??statement.user?.email??'—'}</div></div>
    <div className="rounded-xl border p-3"><div className="text-xs text-slate-500">Total items</div><div className="mt-1 font-bold">{statement.total_items}</div></div>
    <div className="rounded-xl border p-3"><div className="text-xs text-slate-500">Total amount</div><div className="mt-1 font-bold">{money(statement.total_amount_minor,statement.currency)}</div></div>
   </div>
   <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <div><span className="text-xs text-slate-500">Processed</span><div className="font-bold">{statement.processed_items}</div></div>
    <div><span className="text-xs text-slate-500">Successful</span><div className="font-bold text-emerald-700">{statement.successful_items}</div></div>
    <div><span className="text-xs text-slate-500">Failed</span><div className="font-bold text-red-700">{statement.failed_items}</div></div>
    <div><span className="text-xs text-slate-500">Pending</span><div className="font-bold text-amber-700">{statement.pending_items}</div></div>
    <div><span className="text-xs text-slate-500">Successful value</span><div className="font-bold">{money(statement.successful_amount_minor,statement.currency)}</div></div>
   </div>
   <div className="mt-6 overflow-x-auto"><table className="w-full min-w-[900px] text-left text-xs"><thead><tr className="border-b bg-slate-50"><th className="p-3">#</th><th className="p-3">Recipient</th><th className="p-3">Product</th><th className="p-3">Amount</th><th className="p-3">Status</th><th className="p-3">Transaction</th><th className="p-3">Provider Ref.</th><th className="p-3">Failure</th></tr></thead><tbody>{statement.items.map(item=><tr key={item.id} className="border-b"><td className="p-3">{item.sequence}</td><td className="p-3 font-mono">{item.recipient??'—'}</td><td className="p-3">{item.product?.name??'—'}</td><td className="p-3">{money(item.amount_minor,statement.currency)}</td><td className="p-3 font-semibold">{item.status}</td><td className="p-3 font-mono">{item.transaction?.reference??'—'}</td><td className="p-3 font-mono">{item.transaction?.provider_reference??'—'}</td><td className="p-3">{item.error_message??'—'}</td></tr>)}</tbody></table></div>
   <div className="mt-5 border-t pt-4 text-xs text-slate-500"><div>Created: {statement.created_at??'—'}</div><div className="mt-1">Generated from the recorded bulk operation. This statement does not alter financial records.</div></div>
  </div>
 </div></>;
}
