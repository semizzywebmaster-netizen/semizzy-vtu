import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type AddonEvent = {
  id: number;
  event: string;
  from_status: string | null;
  to_status: string | null;
  message: string | null;
  created_at: string | null;
};

type Addon = {
  id: number;
  identifier: string;
  name: string;
  version: string;
  status: string;
  last_error: string | null;
  dependencies: Array<string | { identifier?: string }>;
  permissions: string[];
  installed_at: string | null;
  activated_at: string | null;
  events: AddonEvent[];
};

type Props = { addons: Addon[] };

const statusClass: Record<string, string> = {
  active: 'bg-emerald-100 text-emerald-800',
  installed: 'bg-blue-100 text-blue-800',
  inactive: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-800',
  archived: 'bg-slate-200 text-slate-700',
  installing: 'bg-violet-100 text-violet-800',
  validating: 'bg-violet-100 text-violet-800',
  draft: 'bg-slate-100 text-slate-700',
};

export default function Addons({ addons }: Props) {
  const [showRegister, setShowRegister] = useState(false);

  const submitRegister = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = {
      identifier: String(form.get('identifier') ?? ''),
      name: String(form.get('name') ?? ''),
      version: String(form.get('version') ?? ''),
      compatibility: String(form.get('compatibility') ?? '') || null,
      dependencies: String(form.get('dependencies') ?? '').split(',').map((v) => v.trim()).filter(Boolean),
      permissions: String(form.get('permissions') ?? '').split(',').map((v) => v.trim()).filter(Boolean),
    };
    router.post('/admin/addons/register', payload, {
      onSuccess: () => {
        setShowRegister(false);
        event.currentTarget.reset();
      },
    });
  };

  const action = (url: string, confirmText?: string) => {
    if (confirmText && !window.confirm(confirmText)) return;
    router.post(url);
  };

  return (
    <>
      <Head title="Addon Manager" />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-7xl">
          <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
          <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
              <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Addon Manager</h1>
              <p className="mt-2 text-sm text-slate-600">Register, validate, install, activate, disable, and archive Core addons.</p>
            </div>
            <button onClick={() => setShowRegister((v) => !v)} className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">
              {showRegister ? 'Close' : '+ Register addon'}
            </button>
          </div>

          <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
            <p className="font-bold text-slate-900">Activation safety</p>
            <p className="mt-1 text-sm leading-6 text-slate-600">
              Installation validates the manifest and dependency contract first. Core does not execute arbitrary addon code during this foundation stage.
            </p>
          </section>

          {showRegister && (
            <form onSubmit={submitRegister} className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-2">
              {[
                ['identifier', 'Identifier', 'vtu'],
                ['name', 'Name', 'VTU Services'],
                ['version', 'Version', '1.0.0'],
                ['compatibility', 'Core compatibility', '>=2.0.0'],
                ['dependencies', 'Dependencies (comma separated)', 'payments,notifications'],
                ['permissions', 'Permissions (comma separated)', 'catalogue.read,transactions.create'],
              ].map(([name, label, placeholder]) => (
                <label key={name} className="text-sm font-semibold text-slate-700">
                  {label}
                  <input name={name} placeholder={placeholder} required={name === 'identifier' || name === 'name' || name === 'version'}
                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal outline-none focus:border-slate-500" />
                </label>
              ))}
              <div className="md:col-span-2">
                <button type="submit" className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Register manifest</button>
              </div>
            </form>
          )}

          <div className="mt-6 grid gap-4">
            {addons.length === 0 && (
              <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No addons registered yet.</div>
            )}
            {addons.map((addon) => (
              <article key={addon.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="text-lg font-extrabold text-slate-900">{addon.name}</h2>
                      <span className={statusClass[addon.status] ?? 'bg-slate-100 text-slate-700'}>{addon.status}</span>
                    </div>
                    <p className="mt-1 text-xs font-mono text-slate-500">{addon.identifier} · v{addon.version}</p>
                    {addon.last_error && <p className="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700">{addon.last_error}</p>}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {(addon.status === 'draft' || addon.status === 'failed' || addon.status === 'inactive') && (
                      <button onClick={() => action('/admin/addons/' + addon.id + '/install')} className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Install</button>
                    )}
                    {(addon.status === 'installed' || addon.status === 'inactive') && (
                      <button onClick={() => action('/admin/addons/' + addon.id + '/activate')} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Activate</button>
                    )}
                    {addon.status === 'active' && (
                      <button onClick={() => action('/admin/addons/' + addon.id + '/disable')} className="rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-white">Disable</button>
                    )}
                    {['draft', 'installed', 'inactive', 'failed'].includes(addon.status) && (
                      <button onClick={() => action('/admin/addons/' + addon.id + '/archive', 'Archive this addon?')} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Archive</button>
                    )}
                  </div>
                </div>

                <div className="mt-5 grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-2">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Dependencies</p>
                    <p className="mt-1 text-sm text-slate-600">{addon.dependencies.length ? addon.dependencies.map((d) => typeof d === 'string' ? d : d.identifier).filter(Boolean).join(', ') : 'None'}</p>
                  </div>
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Recent lifecycle events</p>
                    <div className="mt-1 space-y-1">
                      {addon.events.length ? addon.events.slice(0, 4).map((event) => (
                        <p key={event.id} className="text-xs text-slate-600"><span className="font-semibold">{event.event}</span> — {event.message}</p>
                      )) : <p className="text-sm text-slate-500">No events.</p>}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </div>
      </main>
    </>
  );
}
