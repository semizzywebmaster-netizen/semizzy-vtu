import { Head, usePage } from '@inertiajs/react';

type SharedProps = {
  auth?: { user?: { name?: string; role?: string } | null };
  navigation?: { unreadNotifications?: number };
};

export default function Dashboard() {
  const shared = usePage<SharedProps>().props;
  const user = shared.auth?.user;
  const unreadCount = shared.navigation?.unreadNotifications ?? 0;
  const adminLinks = user?.role === 'ADMIN' || user?.role === 'STAFF';

  const cards = [
    { title: 'Notifications', description: unreadCount ? `${unreadCount} unread update${unreadCount === 1 ? '' : 's'}` : 'No unread notifications', href: '/notifications' },
    { title: 'Support centre', description: 'Create a ticket or follow up on an existing request.', href: '/support' },
    { title: 'Profile & security', description: 'Review your account details and change your password.', href: '/profile' },
  ];

  return (
    <>
      <Head title="Dashboard" />
      <main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8">
        <div className="mx-auto max-w-6xl">
          <header className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p className="text-sm font-bold text-indigo-700">SEMIZZY ONE</p>
            <h1 className="mt-2 text-3xl font-extrabold text-slate-900">Welcome{user?.name ? `, ${user.name}` : ''}</h1>
            <p className="mt-3 max-w-2xl text-slate-600">Your platform workspace. Live service metrics will appear only when supported by verified application data—no demo balances or fabricated activity.</p>
          </header>

          <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {cards.map(card => (
              <a key={card.href} href={card.href} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300">
                <h2 className="font-bold text-slate-900">{card.title}</h2>
                <p className="mt-2 text-sm leading-6 text-slate-600">{card.description}</p>
                <span className="mt-4 inline-block text-sm font-semibold text-indigo-700">Open →</span>
              </a>
            ))}
          </section>

          {adminLinks && (
            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
              <h2 className="font-bold text-slate-900">Operations</h2>
              <p className="mt-1 text-sm text-slate-600">Your account permissions determine which operations are available.</p>
              <div className="mt-4 flex flex-wrap gap-3">
                <a className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="/admin/providers">Providers</a>
                <a className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="/admin/catalogue">Catalogue</a>
                {user?.role === 'ADMIN' && <><a className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="/admin/addons">Addons</a><a className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="/admin/security-events">Security events</a></>}
                <a className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold" href="/admin/health">System health</a>
              </div>
            </section>
          )}

          <nav className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 p-2 backdrop-blur md:static md:mt-8 md:border-0 md:bg-transparent">
            <div className="mx-auto flex max-w-6xl justify-around text-xs font-semibold text-slate-600 md:justify-start md:gap-5">
              <a href="/dashboard" aria-current="page" className="p-2 text-indigo-700">Home</a>
              <a href="/dashboard" className="p-2">Services</a>
              <a href="/dashboard" className="p-2">Transactions</a>
              <a href="/notifications" className="relative p-2">Notifications{unreadCount > 0 && <span className="ml-1 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}</a>
              <a href="/profile" className="p-2">Profile</a>
            </div>
          </nav>
        </div>
      </main>
    </>
  );
}
