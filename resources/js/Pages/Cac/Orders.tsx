import { Head, Link } from '@inertiajs/react';

type Order = { id: number; reference: string; status: string; service_type: string; total_minor: number; currency: string; created_at: string; product: { name: string } | null; };
export default function Orders({ orders }: { orders: { data: Order[]; links: { url: string | null; label: string; active: boolean }[] } }) {
  return <>
    <Head title="CAC Applications" />
    <main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-5xl">
      <div className="mb-5 flex items-center justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-slate-500">CAC</p><h1 className="text-2xl font-black">My applications</h1></div><Link href="/cac" className="rounded-xl bg-slate-900 px-4 py-3 font-bold text-white">New application</Link></div>
      <div className="space-y-3">{orders.data.length ? orders.data.map(order => <Link key={order.id} href={\`/cac/orders/\${order.id}\`} className="block rounded-2xl border bg-white p-5 hover:shadow-sm"><div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"><div><h2 className="font-black">{order.reference}</h2><p className="text-sm text-slate-500">{order.product?.name || order.service_type}</p></div><div className="text-left md:text-right"><p className="font-bold">{order.status}</p><p className="text-sm text-slate-500">{order.currency} {(Number(order.total_minor) / 100).toLocaleString()}</p></div></div></Link>) : <div className="rounded-2xl border bg-white p-8 text-center text-slate-500">No CAC applications yet.</div>}</div>
    </div></main>
  </>;
}
