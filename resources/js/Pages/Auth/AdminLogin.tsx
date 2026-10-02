import { Head } from '@inertiajs/react';

export default function AdminLogin() {
  return <><Head title="Administrator sign in" /><main className="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-12">
    <section className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
      <a href="/" className="text-sm font-bold text-indigo-700">SEMIZZY ONE</a><h1 className="mt-5 text-2xl font-extrabold">Administrator sign in</h1>
      <p className="mt-2 text-sm leading-6 text-slate-600">Authentication form wiring is pending the full authentication implementation. This screen does not grant access by itself.</p>
      <div className="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Administrator access is unavailable until backend authentication and role policies are configured.</div>
      <a href="/" className="mt-6 inline-block text-sm font-semibold text-indigo-700">Return to home</a>
    </section>
  </main></>;
}
