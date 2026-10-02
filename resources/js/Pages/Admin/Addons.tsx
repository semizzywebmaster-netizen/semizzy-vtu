import { Head, Link } from '@inertiajs/react';

export default function Addons() {
  return <><Head title="Addon Manager" /><main className="min-h-screen bg-slate-50 p-6 md:p-10"><div className="mx-auto max-w-6xl">
    <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
    <h1 className="mt-2 text-3xl font-extrabold">Addon Manager</h1><p className="mt-2 text-slate-600">Lifecycle controls are server-authorized and transactional.</p>
    <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6"><p className="font-bold">Activation safety</p><p className="mt-2 text-sm leading-6 text-slate-600">An addon must pass validation and installation before it can become active. Failed initialization remains inactive and records diagnostics.</p></div>
  </div></main></>;
}
