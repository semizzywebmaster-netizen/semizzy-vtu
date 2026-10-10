import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type AddonRollout = { identifier: string; name: string; version: string; status: string; enabled: boolean; percentage: number };

export default function FeatureRollouts({ addons }: { addons: AddonRollout[] }) {
  const [rows, setRows] = useState(addons);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState('');
  const update = (identifier: string, patch: Partial<AddonRollout>) => setRows((current) => current.map((row) => row.identifier === identifier ? { ...row, ...patch } : row));
  const save = () => {
    setSaving(true);
    setNotice('');
    router.put('/admin/feature-rollouts', { rollouts: rows.map(({ identifier, enabled, percentage }) => ({ identifier, enabled, percentage })) }, {
      preserveScroll: true,
      onSuccess: () => setNotice('Rollout settings saved.'),
      onError: () => setNotice('Could not save rollout settings. Check the fields and try again.'),
      onFinish: () => setSaving(false),
    });
  };

  return (
    <>
      <Head title="Safe Rollout Controls" />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-5xl">
          <Link href="/admin/platform-controls" className="text-sm font-semibold text-indigo-700">← Platform Controls</Link>
          <header className="mt-4">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-indigo-700">Release safety</p>
            <h1 className="mt-2 text-3xl font-extrabold text-slate-950">Safe Rollout Controls</h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Release active addon web routes gradually to a stable percentage of signed-in users. Changes are audited. Administrators retain access for verification and recovery. This controls route access, not financial eligibility, provider verification or product-publishing readiness.</p>
          </header>

          <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
            <strong>Important:</strong> A rollout percentage is not a substitute for staging tests, permissions, provider readiness checks or approval workflows. Keep critical financial changes behind the existing authorization and transaction-state safeguards.
          </div>

          {notice && <p role="status" className="mt-4 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">{notice}</p>}

          <div className="mt-5 space-y-3">
            {rows.map((row) => (
              <section key={row.identifier} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                  <div className="min-w-0">
                    <h2 className="font-bold text-slate-900">{row.name}</h2>
                    <p className="mt-1 break-all text-xs text-slate-500">{row.identifier} · v{row.version} · lifecycle: {row.status}</p>
                  </div>
                  <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" checked={row.enabled} onChange={(event) => update(row.identifier, { enabled: event.target.checked })} className="h-4 w-4 rounded border-slate-300" />
                    Rollout enabled
                  </label>
                </div>
                <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-center">
                  <label className="block">
                    <span className="mb-2 block text-sm font-semibold text-slate-700">Eligible signed-in users</span>
                    <input type="range" min={0} max={100} step={5} value={row.percentage} onChange={(event) => update(row.identifier, { percentage: Number(event.target.value) })} disabled={!row.enabled} className="w-full accent-indigo-600" />
                    <span className="mt-1 block text-xs text-slate-500">Stable user assignment; the same user remains in the same rollout bucket.</span>
                  </label>
                  <div className="rounded-xl bg-slate-50 px-4 py-3 text-center"><span className="text-2xl font-extrabold text-slate-900">{row.enabled ? row.percentage : 0}%</span><span className="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Rollout</span></div>
                </div>
              </section>
            ))}
            {rows.length === 0 && <p className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No addons are available to configure yet.</p>}
          </div>

          <div className="sticky bottom-3 mt-6 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
            <button type="button" disabled={saving || rows.length === 0} onClick={save} className="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{saving ? 'Saving…' : 'Save rollout settings'}</button>
          </div>
        </div>
      </main>
    </>
  );
}
