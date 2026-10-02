import { Head, router, useForm } from '@inertiajs/react';

type UserRow = {
  id: number; name: string; email: string; role: string; status: string;
  emailVerified: boolean; createdAt: string | null; isSelf: boolean;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Props = {
  users: { data: UserRow[]; links: PageLink[]; total: number };
  filters: { search?: string; role?: string; status?: string };
};

export default function Users({ users, filters }: Props) {
  const form = useForm({ search: filters.search ?? '', role: filters.role ?? '', status: filters.status ?? '' });
  const applyFilters = (event: React.FormEvent) => {
    event.preventDefault();
    router.get('/admin/users', form.data, { preserveState: true, preserveScroll: true, replace: true });
  };
  const update = (user: UserRow) => {
    const role = window.prompt(`Role for ${user.name} (ADMIN, STAFF, SUPPORT, USER)`, user.role);
    if (!role) return;
    const normalizedRole = role.trim().toUpperCase();
    if (!['ADMIN', 'STAFF', 'SUPPORT', 'USER'].includes(normalizedRole)) return;
    const status = window.prompt(`Status for ${user.name} (active, suspended, disabled)`, user.status);
    if (!status) return;
    const normalizedStatus = status.trim().toLowerCase();
    if (!['active', 'suspended', 'disabled'].includes(normalizedStatus)) return;
    router.patch(`/admin/users/${user.id}`, { role: normalizedRole, status: normalizedStatus }, { preserveScroll: true });
  };

  return <><Head title="Users & staff" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <header><p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Users & staff</h1><p className="mt-1 text-sm text-slate-600">{users.total} accounts · Passwords and secrets are never displayed here.</p></header>
    <form onSubmit={applyFilters} className="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
      <input className="rounded-xl border border-slate-300 p-3 sm:col-span-2" placeholder="Search name or email" value={form.data.search} onChange={e=>form.setData('search',e.target.value)} />
      <select className="rounded-xl border border-slate-300 p-3" value={form.data.role} onChange={e=>form.setData('role',e.target.value)}><option value="">All roles</option>{['ADMIN','STAFF','SUPPORT','USER'].map(role=><option key={role}>{role}</option>)}</select>
      <select className="rounded-xl border border-slate-300 p-3" value={form.data.status} onChange={e=>form.setData('status',e.target.value)}><option value="">All statuses</option>{['active','suspended','disabled'].map(status=><option key={status}>{status}</option>)}</select>
      <button className="rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white sm:col-span-4">Apply filters</button>
    </form>
    <section className="mt-5 space-y-3">{users.data.length ? users.data.map(user=><article key={user.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0"><h2 className="font-bold text-slate-900">{user.name}{user.isSelf ? ' (you)' : ''}</h2><p className="break-all text-sm text-slate-600">{user.email}</p><p className="mt-1 text-xs text-slate-500">{user.role} · {user.status} · {user.emailVerified ? 'Email verified' : 'Email unverified'}</p></div><button disabled={user.isSelf} onClick={()=>update(user)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold disabled:opacity-40">Change role/status</button></div></article>) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">No accounts match these filters.</div>}</section>
    <div className="mt-4 flex flex-wrap gap-2">{users.links.map((link,i)=><button key={i} disabled={!link.url} onClick={()=>link.url&&router.visit(link.url)} className={`rounded-lg border px-3 py-2 text-sm ${link.active?'border-indigo-600 bg-indigo-50':'border-slate-200 bg-white'} disabled:opacity-40`} dangerouslySetInnerHTML={{__html:link.label}} />)}</div>
    <nav className="mt-8 flex flex-wrap gap-4 text-sm font-semibold"><a href="/dashboard">Dashboard</a><a href="/admin/providers">Providers</a><a href="/admin/catalogue">Catalogue</a><a href="/admin/addons">Addons</a></nav>
  </div></main></>;
}
