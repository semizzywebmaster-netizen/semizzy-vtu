import { Head, Link } from '@inertiajs/react';

export default function SendMoney() {
  return <main className="min-h-screen bg-slate-50 pb-24 text-slate-900">
    <Head title="Send Money" />
    <div className="mx-auto max-w-lg px-4 py-6">
      <Link href="/dashboard" className="text-sm font-bold text-indigo-700">← Back to Home</Link>
      <section className="mt-5 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">User-to-user transfer</p>
        <h1 className="mt-1 text-2xl font-black">Send Money</h1>
        <p className="mt-2 text-sm leading-6 text-slate-500">Transfer wallet funds to another SEMIZZY ONE user by username or verified account identifier.</p>
        <div className="mt-6 space-y-3">
          <label className="block"><span className="text-xs font-bold text-slate-500">Recipient username</span><input disabled className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3" placeholder="@username" /></label>
          <label className="block"><span className="text-xs font-bold text-slate-500">Amount</span><input disabled className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-3" placeholder="₦0.00" /></label>
          <div className="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">The user-to-user transfer channel is not enabled yet. This screen intentionally does not simulate a transfer or success response.</div>
        </div>
      </section>
    </div>
  </main>;
}
