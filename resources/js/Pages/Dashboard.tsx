import { Head, usePage } from '@inertiajs/react';
import CoreMobileNav from '../Components/CoreMobileNav';

type Metric = { label: string; value: number; description: string };
type QuickLink = { label: string; url: string };
type SharedProps = { navigation?: { unreadNotifications?: number } };
type Props = { role: string; metrics: Metric[]; quickLinks: QuickLink[] };

export default function Dashboard({ role, metrics, quickLinks }: Props) {
  const unreadCount = usePage<SharedProps>().props.navigation?.unreadNotifications ?? 0;
  return (
    <>
      <Head title="Dashboard" />
      <main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8">
        <div className="mx-auto max-w-6xl">
          <header className="rounded-2xl bg-gradient-to-br from-slate-950 to-indigo-950 p-6 text-white md:p-8">
            <p className="text-sm font-semibold tracking-wide text-indigo-200">SEMIZZY ONE · CORE</p>
            <h1 className="mt-2 text-3xl font-extrabold">Dashboard</h1>
            <p className="mt-2 text-sm text-slate-300">Your workspace, with live counts from the current platform database.</p>
            <span className="mt-4 inline-flex rounded-full border border-white/20 px-3 py-1 text-xs font-bold">{role}</span>
          </header>

          <section aria-label="Current platform metrics" className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {metrics.map(metric => (
              <article key={metric.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p className="text-sm font-semibold text-slate-500">{metric.label}</p>
                <p className="mt-2 text-3xl font-extrabold tabular-nums text-slate-900">{metric.value.toLocaleString()}</p>
                <p className="mt-2 text-xs text-slate-500">{metric.description}</p>
              </article>
            ))}
          </section>

          <section className="mt-7">
            <h2 className="text-lg font-bold text-slate-900">Quick access</h2>
            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {quickLinks.map(link => (
                <a key={link.url} href={link.url} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 font-semibold text-slate-800 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50">
                  <span>{link.label}</span><span aria-hidden="true" className="text-indigo-700">→</span>
                </a>
              ))}
            </div>
          </section>

          <p className="mt-8 text-xs text-slate-500">No demo balances, fabricated charts, or sample transactions are shown. Financial features remain unavailable until a separately reviewed addon is installed and enabled.</p>

          {!['ADMIN', 'STAFF', 'SUPPORT'].includes(role) && <CoreMobileNav active="home" unreadCount={unreadCount} />}
        </div>
      </main>
    </>
  );
}
