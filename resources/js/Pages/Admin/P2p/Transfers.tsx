import { Head } from '@inertiajs/react';

type Transfer = {
  id:number; reference:string; amount_minor:string; fee_minor:string; currency:string; status:string; note?:string|null; created_at:string;
  sender?:{id?:number;username?:string;email?:string}|null; recipient?:{id?:number;username?:string;email?:string}|null;
};
export default function Transfers({transfers}:{transfers:{data:Transfer[];links?:any[]}}){
 return <div className="p-6 space-y-6"><Head title="P2P Transfers" />
  <div><h1 className="text-2xl font-semibold">P2P Transfers</h1><p className="text-sm opacity-70">Internal wallet transfer audit history.</p></div>
  <div className="overflow-x-auto rounded border"><table className="min-w-full text-sm"><thead><tr className="border-b text-left"><th className="p-3">Reference</th><th className="p-3">Sender</th><th className="p-3">Recipient</th><th className="p-3">Amount</th><th className="p-3">Status</th><th className="p-3">Date</th></tr></thead>
   <tbody>{(transfers?.data??[]).map(t=><tr key={t.id} className="border-b"><td className="p-3 font-medium">{t.reference}</td><td className="p-3">{t.sender?.username||t.sender?.email||t.sender?.id||'—'}</td><td className="p-3">{t.recipient?.username||t.recipient?.email||t.recipient?.id||'—'}</td><td className="p-3">₦{(Number(t.amount_minor)/100).toFixed(2)}</td><td className="p-3">{t.status}</td><td className="p-3">{t.created_at}</td></tr>)}</tbody>
  </table></div>
 </div>;
}
