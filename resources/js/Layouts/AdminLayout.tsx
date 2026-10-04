import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useState } from 'react';

type MenuItem = {
  id: string; label: string; url: string; icon?: string; section: string;
  addon?: string; addonName?: string;
};
type PageProps = {
  auth?: { user?: { name?: string; role?: string } | null };
  navigation?: { admin?: { items?: MenuItem[] } };
};

const icons: Record<string, string> = {
  home: '⌂', users: '◉', puzzle: '◇', server: '▣', catalogue: '▤',
  pricing: '₦', api: '{}', bell: '♧', shield: '◈', support: '?',
  settings: '⚙', audit: '≡', profile: '◎',
};

function isActive(url: string): boolean {
  if (url === '/dashboard') return window.location.pathname === '/dashboard';
  return window.location.pathname === url || window.location.pathname.startsWith(url + '/');
}

export default function AdminLayout({ children }: PropsWithChildren) {
  const page = usePage<PageProps>();
  const [open, setOpen] = useState(false);
  const user = page.props.auth?.user;
  const items = page.props.navigation?.admin?.items ?? [];
  const isAdminArea = ['ADMIN', 'STAFF', 'SUPPORT'].includes(user?.role ?? '');
  if (!isAdminArea) return <>{children}</>;

  const grouped = items.reduce<Record<string, MenuItem[]>>((groups, item) => {
    const section = item.section || 'core';
    (groups[section] ??= []).push(item);
    return groups;
  }, {});

  const sectionLabels: Record<string, string> = { core: 'Core', addons: 'Active Addons', account: 'Account' };

  const nav = (mobile = false) => (
    <nav aria-label="Admin navigation" className="space-y-1 p-3">
      {Object.entries(grouped).map(([section, sectionItems]) => (
        <div key={section} className="mb-5">
          <p className="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
            {sectionLabels[section] ?? section}
          </p>
          <div className="space-y-1">
            {sectionItems.map(item => {
              const active = isActive(item.url);
              return (
                <Link key={item.id} href={item.url} onClick={() => mobile && setOpen(false)}
                  aria-current={active ? 'page' : undefined}
                  className={
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition ' +
                    (active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900')
                  }>
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold">
                    {icons[item.icon ?? ''] ?? '•'}
                  </span>
                  <span className="min-w-0 flex-1 truncate">{item.label}</span>
                  {item.addonName && <span className="hidden text-[9px] font-bold uppercase text-slate-400 xl:inline">{item.addonName}</span>}
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 overflow-y-auto border-r border-slate-200 bg-white lg:block">
        <div className="border-b border-slate-200 px-5 py-5">
          <Link href="/dashboard" className="block">
            <p className="text-xs font-bold tracking-[0.18em] text-indigo-600">SEMIZZY ONE</p>
            <p className="mt-1 text-lg font-extrabold">Admin Console</p>
            <p className="mt-1 text-xs text-slate-500">{user?.name ?? 'Administrator'} · {user?.role ?? ''}</p>
          </Link>
        </div>
        <div className="h-[calc(100vh-106px)] overflow-y-auto">{nav()}</div>
      </aside>

      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <button aria-label="Close admin menu" className="absolute inset-0 bg-slate-950/40" onClick={() => setOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-[86%] max-w-sm overflow-y-auto bg-white shadow-2xl">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-5">
              <div><p className="text-xs font-bold tracking-[0.18em] text-indigo-600">SEMIZZY ONE</p><p className="font-extrabold">Admin Console</p></div>
              <button aria-label="Close admin menu" className="rounded-lg px-3 py-2 text-xl" onClick={() => setOpen(false)}>×</button>
            </div>
            {nav(true)}
          </aside>
        </div>
      )}

      <div className="lg:pl-72">
        <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
          <button aria-label="Open admin menu" className="rounded-xl border border-slate-200 px-3 py-2 text-lg" onClick={() => setOpen(true)}>☰</button>
          <div className="min-w-0"><p className="truncate text-sm font-extrabold">SEMIZZY ONE Admin</p><p className="text-[11px] text-slate-500">{user?.role ?? ''}</p></div>
        </header>
        <div>{children}</div>
      </div>
    </div>
  );
}
