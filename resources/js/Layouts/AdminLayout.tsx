import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, useEffect, useState } from 'react';

type MenuItem = {
  id: string; label: string; url: string; icon?: string; section: string;
  addon?: string; addonName?: string;
};
type SearchResult = { type: string; title: string; description: string; url: string };
type PageProps = {
  auth?: { user?: { name?: string; role?: string } | null };
  navigation?: { admin?: { items?: MenuItem[] } };
  platform?: { platform_name?: string };
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
  const [search, setSearch] = useState('');
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);

  useEffect(() => {
    const query = search.trim();
    if (query.length < 2) {
      setSearchResults([]);
      setSearchLoading(false);
      return;
    }
    setSearchLoading(true);
    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      fetch('/admin/global-search?q=' + encodeURIComponent(query), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: controller.signal,
      }).then((response) => {
        if (!response.ok) throw new Error('Search unavailable');
        return response.json();
      }).then((data: { results?: SearchResult[] }) => {
        setSearchResults(Array.isArray(data.results) ? data.results : []);
        setSearchLoading(false);
      }).catch((error: unknown) => {
        if (error instanceof Error && error.name !== 'AbortError') {
          setSearchResults([]);
          setSearchLoading(false);
        }
      });
    }, 250);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [search]);
  const user = page.props.auth?.user;
  const items = page.props.navigation?.admin?.items ?? [];
  const siteName = page.props.platform?.platform_name || 'SEMIZZY ONE';
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
            <p className="text-xs font-bold tracking-[0.18em] text-indigo-600">{siteName}</p>
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
              <div><p className="text-xs font-bold tracking-[0.18em] text-indigo-600">{siteName}</p><p className="font-extrabold">Admin Console</p></div>
              <button aria-label="Close admin menu" className="rounded-lg px-3 py-2 text-xl" onClick={() => setOpen(false)}>×</button>
            </div>
            {nav(true)}
          </aside>
        </div>
      )}

      <div className="lg:pl-72">
        <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-slate-200 bg-[color:var(--so-surface)]/95 px-4 py-3 backdrop-blur">
          <button aria-label="Open admin menu" className="rounded-xl border border-slate-200 px-3 py-2 text-lg lg:hidden" onClick={() => setOpen(true)}>☰</button>
          <div className="hidden min-w-0 shrink-0 lg:block"><p className="truncate text-sm font-extrabold">{siteName} Admin</p><p className="text-[11px] text-slate-500">{user?.role ?? ''}</p></div>
          <div className="relative min-w-0 flex-1">
            <label className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 focus-within:border-indigo-400 focus-within:bg-white">
              <span aria-hidden="true" className="text-slate-400">⌕</span>
              <input value={search} onChange={(event) => { setSearch(event.target.value); setSearchOpen(true); }} onFocus={() => setSearchOpen(true)} onKeyDown={(event) => { if (event.key === 'Escape') setSearchOpen(false); }} aria-label="Search users, providers, services and support tickets" placeholder="Search users, providers, products, tickets…" className="w-full min-w-0 bg-transparent text-sm outline-none placeholder:text-slate-400" />
              {search !== '' && <button type="button" onClick={() => { setSearch(''); setSearchResults([]); setSearchOpen(false); }} aria-label="Clear search" className="text-slate-400 hover:text-slate-700">×</button>}
            </label>
            {searchOpen && search.trim().length >= 2 && (
              <div className="absolute left-0 right-0 top-full z-50 mt-2 max-h-[70vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                {searchLoading ? <p className="px-3 py-4 text-sm text-slate-500">Searching…</p> : searchResults.length > 0 ? searchResults.map((result, index) => (
                  <Link key={result.type + result.url + index} href={result.url} onClick={() => { setSearchOpen(false); setSearch(''); setOpen(false); }} className="block rounded-xl px-3 py-2.5 hover:bg-slate-50">
                    <span className="flex items-center justify-between gap-2"><span className="truncate text-sm font-semibold text-slate-900">{result.title}</span><span className="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-500">{result.type}</span></span>
                    <span className="mt-1 block truncate text-xs text-slate-500">{result.description}</span>
                  </Link>
                )) : <p className="px-3 py-4 text-sm text-slate-500">No matching records found.</p>}
                <button type="button" onClick={() => setSearchOpen(false)} className="w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-semibold text-indigo-700">Close results</button>
              </div>
            )}
          </div>
        </header>
        <div>{children}</div>
      </div>
    </div>
  );
}
