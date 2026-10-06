import { Head, Link } from '@inertiajs/react';

type PlatformSettings = {
  platform_name?: string;
  support_notice?: string;
  default_timezone?: string;
};

export default function Welcome({ appName = 'SEMIZZY ONE', platform }: { appName?: string; platform?: PlatformSettings }) {
  const displayName = platform?.platform_name || appName;

  return <><Head title={displayName} /><main className="min-h-screen bg-slate-50 text-slate-900">
    <header className="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
      <div><div className="text-xl font-extrabold tracking-tight">{displayName}</div></div>
      <div className="flex flex-wrap gap-2"><Link className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold" href="/login">User sign in</Link><Link className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white" href="/admin/login">Administrator sign in</Link></div>
    </header>
    {platform?.support_notice && <div className="mx-auto max-w-7xl px-6"><p role="status" className="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">{platform.support_notice}</p></div>}
    <section className="mx-auto grid max-w-7xl gap-10 px-6 py-20 md:grid-cols-2 md:items-center">
      <div><h1 className="max-w-2xl text-4xl font-extrabold leading-tight md:text-6xl">One secure core. A flexible provider ecosystem.</h1><p className="mt-6 max-w-xl text-lg leading-8 text-slate-600">A modular platform foundation for provider management, service catalogues, operational controls and future service addons.</p>
        <div className="mt-8 flex flex-wrap gap-3"><Link href="/login" className="rounded-xl bg-indigo-700 px-5 py-3 font-semibold text-white">User portal</Link><Link href="/register" className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold">Create account</Link><Link href="/admin/login" className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold">Admin portal</Link><a href="/api/v1/health" className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold">API health</a></div>
      </div>
      <div className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm"><p className="text-sm font-bold text-slate-500">CORE PRINCIPLES</p>{['Database-driven provider inventory','Auditable addon lifecycle','Finance disabled by default','cPanel-compatible deployment'].map((item) => <div key={item} className="mt-4 flex items-center gap-3 rounded-xl bg-slate-50 p-4"><span className="h-2.5 w-2.5 rounded-full bg-indigo-600" /><span className="font-semibold">{item}</span></div>)}</div>
    </section>
    <footer className="border-t border-slate-200 px-6 py-8 text-center text-sm text-slate-500">© {new Date().getFullYear()} {displayName}</footer>
  </main></>;
}
