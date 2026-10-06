import { Head, Link } from '@inertiajs/react';

export default function Withdraw() {
  return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
    <Head title="Withdraw" />
    <div className="mx-auto max-w-lg px-4 py-6">
      <Link href="/dashboard" className="text-sm font-bold text-indigo-700">← Back to Home</Link>
      <section className="mt-5 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Wallet</p>
        <h1 className="mt-1 text-2xl font-black">Withdraw</h1>
        <p className="mt-2 text-sm leading-6 text-slate-500">Withdraw available wallet funds to a verified destination when a withdrawal channel is enabled.</p>
        <div className="mt-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">No withdrawal channel is enabled yet. No withdrawal is simulated from this page.</div>
      </section>
    </div>
  </main>;
}
