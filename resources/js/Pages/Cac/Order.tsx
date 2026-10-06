import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Doc = { id: number; document_type: string; original_name: string; mime_type: string; size_bytes: number; status: string; review_note?: string | null; created_at: string; };
type Hist = { id: number; from_status: string | null; to_status: string; source: string; reason?: string | null; created_at: string; };
type Order = { id: number; reference: string; status: string; service_type: string; customer_name: string | null; business_name: string | null; company_type: string | null; total_minor: number; currency: string; product: { name: string } | null; documents: Doc[]; status_history: Hist[]; };

export default function Order({ order }: { order: Order }) {
  const [type, setType] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const upload = (event: FormEvent) => {
    event.preventDefault();
    if (!file || !type) return;
    router.post(\`/cac/orders/\${order.id}/documents\`, { document_type: type, document: file }, { forceFormData: true, preserveScroll: true, onSuccess: () => { setFile(null); setType(''); } });
  };
  const remove = (id: number) => { if (window.confirm('Delete this document?')) router.delete(\`/cac/orders/\${order.id}/documents/\${id}\`, { preserveScroll: true }); };
  return <>
    <Head title={\`CAC \${order.reference}\`} />
    <main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-5xl space-y-5">
      <header className="rounded-3xl bg-slate-900 p-6 text-white"><p className="text-xs font-bold uppercase tracking-wider text-slate-300">CAC APPLICATION</p><h1 className="mt-2 text-2xl font-black">{order.reference}</h1><p className="mt-1 text-slate-300">{order.product?.name || order.service_type} · {order.status}</p></header>
      <section className="rounded-2xl border bg-white p-5"><h2 className="font-black">Application details</h2><div className="mt-3 grid gap-2 text-sm md:grid-cols-2"><p><b>Applicant:</b> {order.customer_name || '—'}</p><p><b>Business:</b> {order.business_name || '—'}</p><p><b>Company type:</b> {order.company_type || '—'}</p><p><b>Total:</b> {order.currency} {(Number(order.total_minor) / 100).toLocaleString()}</p></div></section>
      <section className="rounded-2xl border bg-white p-5"><h2 className="font-black">Documents</h2>
        {['pending_review', 'documents_required'].includes(order.status) && <form onSubmit={upload} className="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-[1fr_1fr_auto]"><input value={type} onChange={e => setType(e.target.value)} placeholder="Document type e.g. id_card" className="rounded-xl border px-3 py-3" /><input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={e => setFile(e.target.files?.[0] || null)} className="rounded-xl border bg-white px-3 py-2" /><button className="rounded-xl bg-slate-900 px-4 py-3 font-bold text-white">Upload</button></form>}
        <div className="mt-4 space-y-2">{order.documents.length ? order.documents.map(doc => <div key={doc.id} className="flex flex-col gap-2 rounded-xl bg-slate-50 p-3 md:flex-row md:items-center md:justify-between"><div><b>{doc.document_type}</b><div className="text-xs text-slate-500">{doc.original_name} · {doc.status}</div>{doc.review_note && <div className="text-xs text-amber-700">{doc.review_note}</div>}</div><div className="flex gap-2"><a href={\`/cac/orders/\${order.id}/documents/\${doc.id}\`} className="rounded-lg border px-3 py-2 text-sm font-bold">Download</a>{['pending_review', 'documents_required'].includes(order.status) && <button onClick={() => remove(doc.id)} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-600">Delete</button>}</div></div>) : <p className="text-sm text-slate-500">No documents uploaded yet.</p>}</div>
      </section>
      <section className="rounded-2xl border bg-white p-5"><h2 className="font-black">Status history</h2><div className="mt-3 space-y-2">{order.status_history?.length ? order.status_history.map(item => <div key={item.id} className="rounded-xl bg-slate-50 p-3 text-sm"><b>{item.to_status}</b> · {item.source} · {new Date(item.created_at).toLocaleString()}{item.reason && <p className="mt-1 text-slate-600">{item.reason}</p>}</div>) : <p className="text-sm text-slate-500">No status history yet.</p>}</div></section>
    </div></main>
  </>;
}
