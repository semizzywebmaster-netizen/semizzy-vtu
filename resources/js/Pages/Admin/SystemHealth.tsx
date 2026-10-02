import { Head, Link } from '@inertiajs/react';

type HealthCheck = {
  key: string;
  label: string;
  status: 'PASS' | 'WARN' | 'FAIL';
  message: string;
};

type Props = {
  health: {
    status: 'PASS' | 'WARN' | 'FAIL';
    checked_at: string;
    checks: HealthCheck[];
  };
};

const statusClass: Record<HealthCheck['status'], string> = {
  PASS: 'border-emerald-200 bg-emerald-50 text-emerald-800',
  WARN: 'border-amber-200 bg-amber-50 text-amber-800',
  FAIL: 'border-rose-200 bg-rose-50 text-rose-800',
};

export default function SystemHealth({ health }: Props) {
  return (
    <>
      <Head title="System Health" />
      <main className="min-h-screen bg-slate-50 p-6 md:p-10">
        <div className="mx-auto max-w-5xl">
          <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
          <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-3xl font-extrabold text-slate-950">System Health</h1>
              <p className="mt-2 text-slate-600">Live Core checks for cPanel deployment readiness and runtime dependencies.</p>
            </div>
            <div className={`rounded-full border px-4 py-2 text-sm font-bold ${statusClass[health.status]}`}>
              {health.status}
            </div>
          </div>

          <div className="mt-8 grid gap-4">
            {health.checks.map((check) => (
              <section key={check.key} className={`rounded-2xl border p-5 ${statusClass[check.status]}`}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <h2 className="font-bold">{check.label}</h2>
                  <span className="text-xs font-extrabold tracking-wider">{check.status}</span>
                </div>
                <p className="mt-2 text-sm leading-6">{check.message}</p>
              </section>
            ))}
          </div>

          <p className="mt-6 text-xs text-slate-500">Checked {health.checked_at}. These checks report actual runtime state; no synthetic health data is used.</p>
        </div>
      </main>
    </>
  );
}
