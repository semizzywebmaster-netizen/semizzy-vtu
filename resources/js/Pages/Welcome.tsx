import { Head, Link } from '@inertiajs/react';

type PlatformSettings = {
  platform_name?: string;
  support_notice?: string;
  assets?: { logo?: string; banner?: string; hero?: string; favicon?: string };
  business?: { phone?: string; whatsapp?: string; email?: string; address?: string; website?: string };
  social?: Record<string, string>;
};

const principles = [
  ['Provider-ready', 'Connect services through a structured provider ecosystem.'],
  ['Built for control', 'Permissions, audit trails and operational visibility at the core.'],
  ['Addon-ready', 'Expand the platform without rebuilding the foundation.'],
  ['cPanel-friendly', 'Designed around practical PHP, MySQL and shared-hosting deployment.'],
];

export default function Welcome({ appName = 'SEMIZZY ONE', platform }: { appName?: string; platform?: PlatformSettings }) {
  const displayName = platform?.platform_name || appName;
  const media = platform?.assets?.hero || platform?.assets?.banner;

  return (
    <>
      <Head title={displayName} />
      <main className="min-h-screen overflow-hidden bg-[#f7f9fc] text-slate-950">
        <div className="relative">
          <div className="pointer-events-none absolute -left-32 -top-40 h-96 w-96 rounded-full bg-indigo-200/40 blur-3xl" />
          <div className="pointer-events-none absolute -right-32 top-24 h-96 w-96 rounded-full bg-cyan-200/30 blur-3xl" />

          <header className="relative mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10">
            <Link href="/" className="flex items-center gap-3">
              {platform?.assets?.logo ? (
                <img src={platform.assets.logo} alt={displayName} className="h-11 w-auto max-w-[180px] object-contain" />
              ) : (
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-slate-950 text-sm font-black text-white shadow-lg">S1</span>
              )}
              <span className="hidden text-lg font-black tracking-tight sm:block">{displayName}</span>
            </Link>
            <nav className="flex items-center gap-2">
              <Link href="/login" className="rounded-xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-white">Sign in</Link>
              <Link href="/register" className="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5">Get started</Link>
            </nav>
          </header>

          {platform?.support_notice && (
            <div className="relative mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
              <p role="status" className="rounded-2xl border border-indigo-100 bg-white/80 px-5 py-3 text-sm font-medium text-indigo-950 shadow-sm backdrop-blur">{platform.support_notice}</p>
            </div>
          )}

          <section className="relative mx-auto grid max-w-7xl gap-12 px-5 pb-20 pt-14 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:px-10 lg:pb-28 lg:pt-20">
            <div className="flex flex-col justify-center">
              <div className="mb-6 inline-flex w-fit items-center gap-2 rounded-full border border-indigo-100 bg-white px-3.5 py-2 text-xs font-extrabold uppercase tracking-[0.16em] text-indigo-700 shadow-sm">
                <span className="h-2 w-2 rounded-full bg-indigo-600" />
                One platform. Built to grow.
              </div>
              <h1 className="max-w-3xl text-5xl font-black leading-[1.02] tracking-[-0.045em] sm:text-6xl lg:text-7xl">
                A smarter foundation for your digital services.
              </h1>
              <p className="mt-7 max-w-2xl text-lg leading-8 text-slate-600 sm:text-xl">
                {displayName} brings provider management, service catalogues, pricing controls, security and future addons into one clean operational platform.
              </p>
              <div className="mt-9 flex flex-wrap gap-3">
                <Link href="/register" className="rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-extrabold text-white shadow-xl shadow-indigo-600/20 transition hover:-translate-y-0.5 hover:bg-indigo-700">Create your account</Link>
                <Link href="/login" className="rounded-2xl border border-slate-200 bg-white px-6 py-3.5 text-sm font-extrabold text-slate-800 shadow-sm transition hover:-translate-y-0.5">Open user portal</Link>
              </div>
              <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-500">
                <span>✓ Role-based access</span>
                <span>✓ Auditable operations</span>
                <span>✓ Addon-ready architecture</span>
              </div>
            </div>

            <div className="relative">
              <div className="absolute -inset-5 rounded-[2.5rem] bg-gradient-to-br from-indigo-200/50 via-transparent to-cyan-100/50 blur-2xl" />
              <div className="relative overflow-hidden rounded-[2rem] border border-white bg-slate-950 p-3 shadow-2xl shadow-slate-900/20">
                <div className="overflow-hidden rounded-[1.5rem] bg-white">
                  {media ? (
                    <img src={media} alt={displayName} className="h-64 w-full object-cover sm:h-80" />
                  ) : (
                    <div className="relative h-64 overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-700 sm:h-80">
                      <div className="absolute inset-0 opacity-30" style={{ backgroundImage: 'radial-gradient(circle at 20% 20%, white 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
                      <div className="absolute left-7 top-8 max-w-xs text-white">
                        <p className="text-xs font-bold uppercase tracking-[0.2em] text-indigo-200">SEMIZZY ONE</p>
                        <p className="mt-4 text-3xl font-black leading-tight">Your services. One controlled platform.</p>
                      </div>
                    </div>
                  )}
                  <div className="grid gap-3 p-5 sm:grid-cols-2">
                    {principles.map(([title, description]) => (
                      <div key={title} className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p className="font-extrabold">{title}</p>
                        <p className="mt-1.5 text-sm leading-6 text-slate-500">{description}</p>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <section className="border-y border-slate-200/80 bg-white">
          <div className="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:px-8 md:grid-cols-3 lg:px-10">
            <div>
              <p className="text-sm font-extrabold uppercase tracking-[0.16em] text-indigo-600">01 · Manage</p>
              <h2 className="mt-2 text-2xl font-black">Keep operations organised.</h2>
              <p className="mt-2 leading-7 text-slate-500">Manage providers, catalogue entries, pricing and platform settings from a structured control layer.</p>
            </div>
            <div>
              <p className="text-sm font-extrabold uppercase tracking-[0.16em] text-indigo-600">02 · Secure</p>
              <h2 className="mt-2 text-2xl font-black">Know what happened.</h2>
              <p className="mt-2 leading-7 text-slate-500">Role-based access, security events and audit trails help keep important operations accountable.</p>
            </div>
            <div>
              <p className="text-sm font-extrabold uppercase tracking-[0.16em] text-indigo-600">03 · Expand</p>
              <h2 className="mt-2 text-2xl font-black">Add capabilities when ready.</h2>
              <p className="mt-2 leading-7 text-slate-500">The core is designed to support future service addons without cluttering the foundation.</p>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-5 py-16 text-center sm:px-8 lg:px-10 lg:py-20">
          <p className="text-sm font-extrabold uppercase tracking-[0.18em] text-indigo-600">Ready when you are</p>
          <h2 className="mx-auto mt-3 max-w-2xl text-4xl font-black tracking-tight sm:text-5xl">Build your service platform on a cleaner core.</h2>
          <p className="mx-auto mt-5 max-w-xl leading-7 text-slate-500">Start with the platform foundation and expand it as your business grows.</p>
          <div className="mt-8 flex justify-center gap-3">
            <Link href="/register" className="rounded-2xl bg-slate-950 px-6 py-3.5 text-sm font-extrabold text-white shadow-xl transition hover:-translate-y-0.5">Create account</Link>
            <Link href="/admin/login" className="rounded-2xl border border-slate-200 bg-white px-6 py-3.5 text-sm font-extrabold text-slate-800 shadow-sm">Admin sign in</Link>
          </div>
        </section>

        <footer className="border-t border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500">
          © {new Date().getFullYear()} {displayName}. All rights reserved.
        </footer>
      </main>
    </>
  );
}
