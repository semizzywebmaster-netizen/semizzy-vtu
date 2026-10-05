import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

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

const busyStatuses = new Set(['validating', 'installing', 'enabling', 'disabling', 'updating', 'uninstalling']);
const formatDate = (value: string | null) => { if (!value) return 'Not recorded'; const date = new Date(value); return Number.isNaN(date.getTime()) ? value : date.toLocaleString(); };
const formatEvent = (event: AddonEvent) => { const transition = event.from_status || event.to_status ? `${event.from_status ?? '—'} → ${event.to_status ?? '—'}` : null; return transition ? `${event.event} · ${transition}` : event.event; };

const statusClass: Record<string, string> = {
  active: 'bg-emerald-100 text-emerald-800',
  installed: 'bg-blue-100 text-blue-800',
  inactive: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-800',
  archived: 'bg-slate-200 text-slate-700',
  enabling: 'bg-violet-100 text-violet-800',
  disabling: 'bg-violet-100 text-violet-800',
  updating: 'bg-indigo-100 text-indigo-800',
  uninstalling: 'bg-violet-100 text-violet-800',
  installing: 'bg-violet-100 text-violet-800',
  validating: 'bg-violet-100 text-violet-800',
  draft: 'bg-slate-100 text-slate-700',
};

export default function Addons({ addons }: Props) {
  const [showRegister, setShowRegister] = useState(false);
  const [updatingId, setUpdatingId] = useState<number | null>(null);
  const [processing, setProcessing] = useState<string | null>(null);
  const vtuAddon = useMemo(() => addons.find((addon) => addon.identifier === 'vtu.digital-services'), [addons]);

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
    setProcessing('register');
    router.post('/admin/addons/register', payload, {
      onSuccess: () => {
        setShowRegister(false);
        event.currentTarget.reset();
      },
      onFinish: () => setProcessing(null),
    });
  };

  const submitUpdate = (addon: Addon, event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const dependencies = String(form.get('dependencies') ?? '').split(',').map((v) => v.trim()).filter(Boolean);
    const permissions = String(form.get('permissions') ?? '').split(',').map((v) => v.trim()).filter(Boolean);

    setProcessing(`update:${addon.id}`);
    router.post('/admin/addons/' + addon.id + '/update', {
      identifier: addon.identifier,
      name: String(form.get('name') ?? addon.name),
      version: String(form.get('version') ?? ''),
      compatibility: String(form.get('compatibility') ?? '') || null,
      dependencies,
      permissions,
    }, {
      onSuccess: () => setUpdatingId(null),
      onFinish: () => setProcessing(null),
    });
  };

  const action = (url: string, key: string, confirmText?: string) => {
    if (processing || (confirmText && !window.confirm(confirmText))) return;
    setProcessing(key);
    router.post(url, undefined, { onFinish: () => setProcessing(null) });
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
            {vtuAddon?.status === 'active' && <Link href="/admin/vtu" className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">Open VTU Dashboard</Link>}
            <button disabled={Boolean(processing)} onClick={() => setShowRegister((v) => !v)} className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">
              {showRegister ? 'Close Register Addon' : 'Register Addon'}
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
                  <input name={name} defaultValue={placeholder} placeholder={placeholder} required={name === 'identifier' || name === 'name' || name === 'version'}
                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal outline-none focus:border-slate-500" />
                </label>
              ))}
              <div className="md:col-span-2">
                <button type="submit" disabled={processing === 'register'} className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-60">{processing === 'register' ? 'Registering…' : 'Register manifest'}</button>
              </div>
            </form>
          )}

          <div className="mt-6 grid gap-4">
            {addons.length === 0 && (
              <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">No addons registered yet.</div>
            )}
            {addons.map((addon) => {
              const isBusy = busyStatuses.has(addon.status) || processing !== null;
              return (
              <article key={addon.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="text-lg font-extrabold text-slate-900">{addon.name}</h2>
                      <span className={`rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide ${statusClass[addon.status] ?? 'bg-slate-100 text-slate-700'}`}>{busyStatuses.has(addon.status) ? `${addon.status}…` : addon.status}</span>
                    </div>
                    <p className="mt-1 text-xs font-mono text-slate-500">{addon.identifier} · v{addon.version}</p>
                    {addon.last_error && <p className="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700">{addon.last_error}</p>}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {(addon.status === 'draft' || addon.status === 'failed' || addon.status === 'inactive') && (
                      <button disabled={isBusy} onClick={() => action('/admin/addons/' + addon.id + '/install', `install:${addon.id}`)} className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">{processing === `install:${addon.id}` ? 'Installing…' : 'Install'}</button>
                    )}
                    {(addon.status === 'installed' || addon.status === 'inactive') && (
                      <button disabled={isBusy} onClick={() => action('/admin/addons/' + addon.id + '/activate', `activate:${addon.id}`)} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">{processing === `activate:${addon.id}` ? 'Activating…' : 'Activate'}</button>
                    )}
                    {(addon.status === 'installed' || addon.status === 'active' || addon.status === 'inactive') && (
                      <button onClick={() => setUpdatingId(updatingId === addon.id ? null : addon.id)} className="rounded-lg border border-indigo-300 px-3 py-2 text-xs font-bold text-indigo-700">
                        {updatingId === addon.id ? 'Close update' : 'Update'}
                      </button>
                    )}
                    {addon.status === 'active' && (
                      <button disabled={isBusy} onClick={() => action('/admin/addons/' + addon.id + '/disable', `disable:${addon.id}`)} className="rounded-lg bg-amber-500 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">{processing === `disable:${addon.id}` ? 'Disabling…' : 'Disable'}</button>
                    )}
                    {['installed', 'inactive', 'failed'].includes(addon.status) && (
                      <button disabled={isBusy} onClick={() => action('/admin/addons/' + addon.id + '/uninstall', `uninstall:${addon.id}`, 'Uninstall this addon? The addon will be archived after the uninstall contract is recorded.')} className="rounded-lg border border-red-300 px-3 py-2 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-60">{processing === `uninstall:${addon.id}` ? 'Uninstalling…' : 'Uninstall'}</button>
                    )}
                    {addon.status === 'draft' && (
                      <button disabled={isBusy} onClick={() => action('/admin/addons/' + addon.id + '/archive', `archive:${addon.id}`, 'Archive this addon?')} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-60">{processing === `archive:${addon.id}` ? 'Archiving…' : 'Archive'}</button>
                    )}
                  </div>
                </div>

                {updatingId === addon.id && (
                  <form onSubmit={(event) => submitUpdate(addon, event)} className="mt-5 rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                    <p className="text-sm font-bold text-slate-900">Update addon</p>
                    <p className="mt-1 text-xs text-slate-600">The version must be newer than v{addon.version}. Core validates compatibility and dependencies before committing the update.</p>
                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                      <input name="name" defaultValue={addon.name} placeholder="Addon name" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required />
                      <input name="version" placeholder="New version, e.g. 1.1.0" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" required />
                      <input name="compatibility" defaultValue="" placeholder="Core compatibility, e.g. >=2.0.0" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                      <input name="dependencies" defaultValue={addon.dependencies.map((d) => typeof d === 'string' ? d : d.identifier).filter(Boolean).join(',')} placeholder="Dependencies (comma separated)" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                      <input name="permissions" defaultValue={addon.permissions.join(',')} placeholder="Permissions (comma separated)" className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-2" />
                    </div>
                    <div className="mt-3">
                      <button type="submit" disabled={processing === `update:${addon.id}`} className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">{processing === `update:${addon.id}` ? 'Updating…' : 'Validate & update'}</button>
                    </div>
                  </form>
                )}

                <div className="mt-5 grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-2">
                  <div>
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Dependencies</p>
                    <p className="mt-1 text-sm text-slate-600">{addon.dependencies.length ? addon.dependencies.map((d) => typeof d === 'string' ? d : d.identifier).filter(Boolean).join(', ') : 'None'}</p>
                  </div>
                  <div className="space-y-4">
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">Permissions</p>{addon.permissions.length ? <div className="mt-2 flex flex-wrap gap-1.5">{addon.permissions.map((permission) => <span key={permission} className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">{permission}</span>)}</div> : <p className="mt-1 text-sm text-slate-500">None declared.</p>}</div>
                    <div className="grid gap-3 sm:grid-cols-2"><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">Installed</p><p className="mt-1 text-sm text-slate-600">{formatDate(addon.installed_at)}</p></div><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">Activated</p><p className="mt-1 text-sm text-slate-600">{formatDate(addon.activated_at)}</p></div></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">Recent lifecycle events</p><div className="mt-2 space-y-2">{addon.events.length ? addon.events.slice(0, 4).map((event) => <div key={event.id} className="rounded-lg border border-slate-100 bg-slate-50 p-2.5"><div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span className="text-xs font-bold text-slate-700">{formatEvent(event)}</span><time className="text-[11px] text-slate-400">{formatDate(event.created_at)}</time></div>{event.message && <p className="mt-1 text-xs leading-5 text-slate-600">{event.message}</p>}</div>) : <p className="text-sm text-slate-500">No events.</p>}</div></div>
                  </div>
                </div>
              </article>
              );
            })}
          </div>
        </div>
      </main>
    </>
  );
}
