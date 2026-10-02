import { Head, Link } from '@inertiajs/react';

export default function Providers() {
  return <><Head title="API Providers" /><main className="min-h-screen bg-slate-50 p-6 md:p-10"><div className="mx-auto max-w-6xl">
    <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
    <div className="mt-2 flex flex-wrap items-end justify-between gap-4"><div><h1 className="text-3xl font-extrabold">API Providers</h1><p className="mt-2 text-slate-600">Database-driven provider inventory with verification gates.</p></div><button disabled className="rounded-xl bg-slate-300 px-4 py-2.5 font-semibold text-slate-600">Add provider · coming with CRUD service</button></div>
    <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6"><p className="font-bold">Safety state</p><p className="mt-2 text-sm leading-6 text-slate-600">A provider is not eligible for new transactions until it is both production-verified and enabled. Credentials are stored encrypted and must never be returned to the browser.</p></div>
  </div></main></>;
}
