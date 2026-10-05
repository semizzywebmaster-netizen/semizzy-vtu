import { Head, Link, usePage } from '@inertiajs/react';
import CoreMobileNav from '../Components/CoreMobileNav';

type Metric = { label: string; value: number; description: string };
type QuickLink = { label: string; url: string };
type Workspace = { operations: boolean; staff: boolean; supportOpen: number; supportPending: number };
type SharedProps = { navigation?: { unreadNotifications?: number } };
type Props = { role: string; metrics: Metric[]; quickLinks: QuickLink[]; workspace: Workspace };

const roleCopy: Record<string, { eyebrow: string; title: string; description: string }> = {
  ADMIN: { eyebrow: 'SEMIZZY ONE · ADMIN CONTROL', title: 'Command Center', description: 'Monitor the platform, manage operations and move quickly to the areas that need attention.' },
  STAFF: { eyebrow: 'SEMIZZY ONE · STAFF WORKSPACE', title: 'Operations Center', description: 'A focused workspace for the platform tasks your staff permissions allow you to perform.' },
  SUPPORT: { eyebrow: 'SEMIZZY ONE · SUPPORT', title: 'Support Workspace', description: 'Keep customer requests organised and respond to issues assigned to your team.' },
  USER: { eyebrow: 'SEMIZZY ONE · YOUR ACCOUNT', title: 'Welcome back', description: 'Your account overview, notifications and support access are all in one place.' },
};

function iconFor(label: string) {
  const value = label.toLowerCase();
  if (value.includes('user') || value.includes('account')) return '◉';
  if (value.includes('provider')) return '⌁';
  if (value.includes('addon')) return '◆';
  if (value.includes('product') || value.includes('service')) return '▦';
  if (value.includes('ticket') || value.includes('support')) return '✦';
  if (value.includes('transaction')) return '₦';
  if (value.includes('notification')) return '●';
  return '•';
}

export default function Dashboard({ role, metrics, quickLinks, workspace }: Props) {
  const unreadCount = usePage<SharedProps>().props.navigation?.unreadNotifications ?? 0;
  const copy = roleCopy[role] ?? roleCopy.USER;
  const isAdmin = role === 'ADMIN';
  const isStaff = role === 'STAFF';
  const isOperations = ['ADMIN', 'STAFF', 'SUPPORT'].includes(role);

  return (
    <>
      <Head title={isAdmin ? 'Admin Dashboard' : isStaff ? 'Staff Dashboard' : 'Dashboard'} />
      <main className="min-h-screen bg-slate-50 pb-24">
        <div className="mx-auto max-w-7xl px-4 py-5 md:px-8 md:py-8">
          <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-6 text-white shadow-xl md:p-8">
            <div className="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl" />
            <div className="absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-blue-500/10 blur-3xl" />
            <div className="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
              <div>
                <p className="text-xs font-bold uppercase tracking-[0.18em] text-indigo-200">{copy.eyebrow}</p>
                <h1 className="mt-2 text-3xl font-black tracking-tight md:text-4xl">{copy.title}</h1>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">{copy.description}</p>
              </div>
              <span className="inline-flex w-fit rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-extrabold tracking-wide backdrop-blur">{role}</span>
            </div>
          </section>

          {isStaff && (
            <section className="mt-5 grid gap-3 md:grid-cols-3">
              <Link href="/support" className="rounded-2xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-300 hover:shadow-sm">
                <p className="text-xs font-bold uppercase tracking-wide text-amber-700">Open queue</p>
                <p className="mt-1 text-2xl font-black text-amber-950">{workspace.supportOpen.toLocaleString()}</p>
                <p className="mt-1 text-xs text-amber-800/70">Customer requests awaiting first response</p>
              </Link>
              <Link href="/support" className="rounded-2xl border border-blue-200 bg-blue-50 p-4 transition hover:border-blue-300 hover:shadow-sm">
                <p className="text-xs font-bold uppercase tracking-wide text-blue-700">Pending queue</p>
                <p className="mt-1 text-2xl font-black text-blue-950">{workspace.supportPending.toLocaleString()}</p>
                <p className="mt-1 text-xs text-blue-800/70">Requests awaiting follow-up</p>
              </Link>
              <Link href="/notifications" className="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 transition hover:border-indigo-300 hover:shadow-sm">
                <p className="text-xs font-bold uppercase tracking-wide text-indigo-700">Notifications</p>
                <p className="mt-1 text-2xl font-black text-indigo-950">{unreadCount.toLocaleString()}</p>
                <p className="mt-1 text-xs text-indigo-800/70">Unread operational updates</p>
              </Link>
            </section>
          )}

          {metrics.length > 0 ? (
            <section aria-label="Dashboard metrics" className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
              {metrics.map(metric => (
                <article key={metric.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{metric.label}</p>
                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">{iconFor(metric.label)}</span>
                  </div>
                  <p className="mt-4 text-3xl font-black tabular-nums text-slate-950">{metric.value.toLocaleString()}</p>
                  <p className="mt-2 text-xs leading-5 text-slate-500">{metric.description}</p>
                </article>
              ))}
            </section>
          ) : (
            <section className="mt-5 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
              <p className="font-bold text-slate-900">Nothing to show yet</p>
              <p className="mt-1 text-sm text-slate-500">Your dashboard will populate as platform activity becomes available.</p>
            </section>
          )}

          <div className="mt-7 grid gap-5 lg:grid-cols-[1fr_360px]">
            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <h2 className="text-lg font-black text-slate-950">{isOperations ? 'Operations & quick access' : 'Quick access'}</h2>
                  <p className="mt-1 text-sm text-slate-500">Only areas available to your role and permissions are shown.</p>
                </div>
                {unreadCount > 0 && !isOperations && <Link href="/notifications" className="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{unreadCount} unread</Link>}
              </div>
              <div className="mt-5 grid gap-3 sm:grid-cols-2">
                {quickLinks.map(link => (
                  <Link key={link.url} href={link.url} className="group flex min-h-[72px] items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-indigo-300 hover:bg-indigo-50">
                    <span className="font-bold text-slate-800 group-hover:text-indigo-900">{link.label}</span>
                    <span aria-hidden="true" className="text-lg font-black text-indigo-600 transition group-hover:translate-x-1">→</span>
                  </Link>
                ))}
              </div>
            </section>

            <aside className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
              <h2 className="text-lg font-black text-slate-950">{isAdmin ? 'Control status' : isStaff ? 'Staff access' : 'Account status'}</h2>
              <div className="mt-4 space-y-3">
                <div className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span className="text-sm font-semibold text-slate-600">Access</span><span className="font-bold text-emerald-700">Active</span></div>
                <div className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span className="text-sm font-semibold text-slate-600">Role</span><span className="font-bold text-slate-900">{role}</span></div>
                <div className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span className="text-sm font-semibold text-slate-600">Notifications</span><span className="font-bold text-slate-900">{unreadCount.toLocaleString()} unread</span></div>
              </div>
              <div className="mt-5 rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                <p className="text-sm font-bold text-indigo-950">{isStaff ? 'Permission boundary' : isAdmin ? 'Administrator reminder' : 'Platform note'}</p>
                <p className="mt-1 text-xs leading-5 text-indigo-900/70">{isStaff ? 'Staff actions remain limited by server-side permission middleware. Hidden shortcuts do not grant access, and direct URLs are still protected.' : isAdmin ? 'Only live database records are displayed. Financial and provider actions remain protected by permissions and addon state.' : 'Only real account and platform records are displayed. Business services appear when their approved addons are enabled.'}</p>
              </div>
            </aside>
          </div>

          <footer className="mt-7 flex flex-col gap-2 border-t border-slate-200 pt-5 text-xs text-slate-500 md:flex-row md:items-center md:justify-between">
            <span>SEMIZZY ONE · Core platform</span><span>No demo balances, fabricated transactions or fake success states.</span>
          </footer>
          {!isOperations && <CoreMobileNav active="home" unreadCount={unreadCount} />}
        </div>
      </main>
    </>
  );
}
