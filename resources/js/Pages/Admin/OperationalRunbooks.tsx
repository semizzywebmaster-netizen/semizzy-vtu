import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Runbook = { id: string; title: string; severity: string; steps: string[] };

export default function OperationalRunbooks({ runbooks }: { runbooks: Runbook[] }) {
  const [query, setQuery] = useState('');
  const [expanded, setExpanded] = useState<string | null>(runbooks[0]?.id ?? null);
  const visible = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return runbooks;
    return runbooks.filter((item) => [item.title, item.severity, ...item.steps].join(' ').toLowerCase().includes(term));
  }, [query, runbooks]);

  return (
    <>
      <Head title="Operational Runbooks" />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-5xl">
          <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
          <header className="mt-4">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-indigo-700">Operations & incident response</p>
            <h1 className="mt-2 text-3xl font-extrabold text-slate-950">Operational Runbooks</h1>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Practical response steps for provider incidents, pending transactions, refunds, catalogue syncs, delivery failures and recovery. Follow the live transaction state and authorization rules; never guess or bypass financial safeguards.</p>
          </header>

          <label className="mt-6 block">
            <span className="mb-2 block text-sm font-semibold text-slate-700">Find a runbook</span>
            <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search incidents, steps or severity…" className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </label>

          <div className="mt-5 space-y-3">
            {visible.map((runbook) => (
              <section key={runbook.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <button type="button" onClick={() => setExpanded(expanded === runbook.id ? null : runbook.id)} aria-expanded={expanded === runbook.id} className="flex w-full items-center justify-between gap-3 p-4 text-left md:p-5">
                  <span className="min-w-0">
                    <span className="block font-bold text-slate-900">{runbook.title}</span>
                    <span className="mt-1 block text-xs text-slate-500">{runbook.steps.length} steps · {runbook.severity} priority</span>
                  </span>
                  <span className="text-xl text-slate-500">{expanded === runbook.id ? '−' : '+'}</span>
                </button>
                {expanded === runbook.id && (
                  <ol className="space-y-3 border-t border-slate-100 px-5 py-4 md:px-7">
                    {runbook.steps.map((step, index) => <li key={index} className="flex gap-3 text-sm leading-6 text-slate-700"><span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">{index + 1}</span><span>{step}</span></li>)}
                  </ol>
                )}
              </section>
            ))}
            {visible.length === 0 && <p className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">No runbooks match that search.</p>}
          </div>
        </div>
      </main>
    </>
  );
}
