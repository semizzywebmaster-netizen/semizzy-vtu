import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

type Transfer = {
  id: number; reference: string; amount_minor: string; fee_minor: string;
  currency: string; status: string; note?: string | null;
  sender?: { username?: string; email?: string } | null;
  recipient?: { username?: string; email?: string } | null;
  created_at: string;
};

export default function Transfers({ transfers }: { transfers: { data: Transfer[] } }) {
  const [recipient, setRecipient] = useState('');
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [processing, setProcessing] = useState(false);
  const { props }: any = usePage();

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setProcessing(true);
    router.post('/p2p/transfers', { recipient, amount, note }, {
      preserveScroll: true,
      onFinish: () => setProcessing(false),
      onSuccess: () => { setRecipient(''); setAmount(''); setNote(''); },
    });
  };

  return <div className="p-6 space-y-6">
    <Head title="P2P Transfers" />
    <div><h1 className="text-2xl font-semibold">P2P Transfers</h1><p className="text-sm opacity-70">Send NGN from your SEMIZZY ONE wallet to another user.</p></div>
    {props.flash?.success && <div className="rounded-lg p-3 bg-green-50 text-green-700">{props.flash.success}</div>}
    <form onSubmit={submit} className="max-w-xl space-y-3">
      <input className="w-full rounded border p-3" value={recipient} onChange={e=>setRecipient(e.target.value)} placeholder="Username, email or verified phone" required />
      <input className="w-full rounded border p-3" value={amount} onChange={e=>setAmount(e.target.value)} placeholder="Amount (₦)" inputMode="decimal" type="number" min="0.01" step="0.01" required />
      <input className="w-full rounded border p-3" value={note} onChange={e=>setNote(e.target.value)} placeholder="Note (optional)" maxLength={255} />
      <button disabled={processing} className="rounded px-5 py-3 font-medium disabled:opacity-50">{processing ? 'Sending…' : 'Send Transfer'}</button>
    </form>
    <section><h2 className="text-lg font-semibold mb-3">Recent transfers</h2>
      <div className="space-y-2">{(transfers?.data ?? []).map(t=><div key={t.id} className="rounded border p-3 flex justify-between gap-4">
        <div><div className="font-medium">{t.reference}</div><div className="text-sm opacity-70">{t.status} · {t.created_at}</div></div>
        <div className="font-semibold">₦{(Number(t.amount_minor)/100).toFixed(2)}</div>
      </div>)}</div>
    </section>
  </div>;
}
