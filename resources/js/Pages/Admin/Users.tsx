import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type UserRow = {
  id: number; name: string; username: string; email: string; phone: string | null;
  role: string; status: string; tier: number; emailVerified: boolean; phoneVerified: boolean;
  createdAt: string | null; isSelf: boolean; wallet: { id: number; availableMinor: string; heldMinor: string; currency: string; status: string } | null;
  permissions: Record<string, boolean>;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Tier = { id: number; name: string; dailyLimitMinor: string; balanceLimitMinor: string | null; upgradeLabel: string | null };
type Props = {
  users: { data: UserRow[]; links: PageLink[]; total: number };
  filters: { search?: string; role?: string; status?: string; tier?: string };
  tiers: Tier[];
  permissions: string[];
};

export default function Users({ users, filters, tiers, permissions }: Props) {
  const filterForm = useForm({ search: filters.search ?? '', role: filters.role ?? '', status: filters.status ?? '', tier: filters.tier ?? '' });
  const [editing, setEditing] = useState<UserRow | null>(null);
  const [funding, setFunding] = useState<UserRow | null>(null);
  const [debiting, setDebiting] = useState<UserRow | null>(null);
  const [permissionUser, setPermissionUser] = useState<UserRow | null>(null);
  const [permissionState, setPermissionState] = useState<Record<string, boolean>>({});
  const editForm = useForm({ username: '', role: 'USER', status: 'active', tier: 1, password: '' });
  const fundForm = useForm({ amount: '', note: '' });
  const debitForm = useForm({ amount: '', note: '' });

  const applyFilters = (event: React.FormEvent) => {
    event.preventDefault();
    router.get('/admin/users', filterForm.data, { preserveState: true, preserveScroll: true, replace: true });
  };

  const openEdit = (user: UserRow) => {
    setEditing(user);
    editForm.setData({ username: user.username, role: user.role, status: user.status, tier: user.tier, password: '' });
  };

  const saveEdit = (event: React.FormEvent) => {
    event.preventDefault();
    if (!editing) return;
    editForm.patch(`/admin/users/${editing.id}`, {
      preserveScroll: true,
      onSuccess: () => setEditing(null),
    });
  };

  const toggleEmail = (user: UserRow) => {
    router.post(`/admin/users/${user.id}/verify`, {
      email_verified: !user.emailVerified,
      phone_verified: user.phoneVerified,
    }, { preserveScroll: true });
  };

  const togglePhone = (user: UserRow) => {
    router.post(`/admin/users/${user.id}/verify`, {
      email_verified: user.emailVerified,
      phone_verified: !user.phoneVerified,
    }, { preserveScroll: true });
  };

  const openPermissions = (user: UserRow) => {
    setPermissionUser(user);
    setPermissionState(user.permissions || {});
  };

  const savePermissions = (event: React.FormEvent) => {
    event.preventDefault();
    if (!permissionUser) return;
    router.put(`/admin/users/${permissionUser.id}/permissions`, { permissions: permissionState }, { preserveScroll: true, onSuccess: () => setPermissionUser(null) });
  };

  const walletStatus = (user: UserRow, status: 'active' | 'frozen') => {
    router.post(`/admin/users/${user.id}/wallet-status`, { status }, { preserveScroll: true });
  };

  const submitDebit = (event: React.FormEvent) => {
    event.preventDefault(); if (!debiting) return;
    debitForm.post(`/admin/users/${debiting.id}/debit`, { preserveScroll: true, onSuccess: () => { setDebiting(null); debitForm.reset(); } });
  };

  const toggleAccountFreeze = (user: UserRow) => {
    if (user.isSelf) return;
    router.post(`/admin/users/${user.id}/freeze`, { frozen: user.status !== 'suspended' }, { preserveScroll: true });
  };

  const submitFunding = (event: React.FormEvent) => {
    event.preventDefault();
    if (!funding) return;
    fundForm.post(`/admin/users/${funding.id}/fund`, {
      preserveScroll: true,
      onSuccess: () => { setFunding(null); fundForm.reset(); },
    });
  };

  return <><Head title="Users & accounts" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-7xl">
    <header className="rounded-3xl bg-slate-900 p-6 text-white shadow-xl">
      <p className="text-xs font-semibold uppercase tracking-wider text-slate-300">SEMIZZY ONE · ADMIN</p>
      <div className="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h1 className="text-2xl font-black">Users & accounts</h1><p className="mt-1 text-sm text-slate-300">Edit accounts, manage verification, tiers, roles, status and wallet funding.</p></div><span className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">{users.total} accounts</span></div>
    </header>

    <form onSubmit={applyFilters} className="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-5">
      <input className="rounded-xl border border-slate-300 p-3 md:col-span-2" placeholder="Search name, username, email or phone" value={filterForm.data.search} onChange={e=>filterForm.setData('search',e.target.value)} />
      <select className="rounded-xl border border-slate-300 p-3" value={filterForm.data.role} onChange={e=>filterForm.setData('role',e.target.value)}><option value="">All roles</option>{['ADMIN','STAFF','SUPPORT','USER'].map(role=><option key={role}>{role}</option>)}</select>
      <select className="rounded-xl border border-slate-300 p-3" value={filterForm.data.status} onChange={e=>filterForm.setData('status',e.target.value)}><option value="">All statuses</option>{['active','suspended','disabled'].map(status=><option key={status}>{status}</option>)}</select>
      <select className="rounded-xl border border-slate-300 p-3" value={filterForm.data.tier} onChange={e=>filterForm.setData('tier',e.target.value)}><option value="">All tiers</option>{tiers.map(tier=><option key={tier.id} value={tier.id}>{tier.name}</option>)}</select>
      <button className="rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white md:col-span-5">Apply filters</button>
    </form>

    <section className="mt-5 space-y-3">{users.data.length ? users.data.map(user=><article key={user.id} className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h2 className="font-black text-slate-900">{user.name}{user.isSelf ? ' (you)' : ''}</h2><span className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{user.role}</span><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{'Tier ' + user.tier}</span></div>
          <p className="mt-1 break-all text-sm text-slate-600">@{user.username} · {user.email}</p><p className="mt-1 text-xs text-slate-500">{user.phone || 'No phone'} · {user.status} · {user.emailVerified ? 'Email verified' : 'Email unverified'} · {user.phoneVerified ? 'Phone verified' : 'Phone unverified'}</p></div>
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap"><button type="button" onClick={()=>openEdit(user)} className="rounded-xl bg-slate-900 px-3 py-2 text-sm font-bold text-white">Edit account</button><a href="/admin/profile-change-requests" className="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700">Change requests</a><button type="button" onClick={()=>toggleEmail(user)} className="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">{user.emailVerified ? 'Unverify email' : 'Verify email'}</button><button type="button" onClick={()=>togglePhone(user)} className="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">{user.phoneVerified ? 'Unverify phone' : 'Verify phone'}</button><button type="button" onClick={()=>openPermissions(user)} className="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700">Permissions</button><button type="button" onClick={()=>setFunding(user)} className="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-bold text-white">Credit / Fund</button><button type="button" onClick={()=>setDebiting(user)} disabled={!user.wallet} className="rounded-xl bg-amber-500 px-3 py-2 text-sm font-bold text-white disabled:opacity-40">Debit</button><button type="button" onClick={()=>toggleAccountFreeze(user)} disabled={user.isSelf} className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700 disabled:opacity-40">{user.status==='suspended'?'Unfreeze account':'Freeze account'}</button><button type="button" onClick={()=>user.wallet&&walletStatus(user,user.wallet.status==='frozen'?'active':'frozen')} disabled={!user.wallet} className="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-40">{user.wallet?.status==='frozen'?'Unfreeze wallet':'Freeze wallet'}</button></div>
      </div>
    </article>) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">No accounts match these filters.</div>}</section>

    <div className="mt-4 flex flex-wrap gap-2">{users.links.map((link,i)=><button key={i} disabled={!link.url} onClick={()=>link.url&&router.visit(link.url)} className={`rounded-lg border px-3 py-2 text-sm ${link.active?'border-indigo-600 bg-indigo-50':'border-slate-200 bg-white'} disabled:opacity-40`} dangerouslySetInnerHTML={{__html:link.label}} />)}</div>

    <nav className="mt-8 flex flex-wrap gap-4 text-sm font-semibold"><a href="/dashboard">Dashboard</a><a href="/admin/providers">Providers</a><a href="/admin/catalogue">Catalogue</a><a href="/admin/addons">Addons</a></nav>

    {editing && <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4"><div className="mx-auto mt-8 max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
      <div className="flex items-start justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Admin account editor</p><h2 className="mt-1 text-2xl font-black">Edit {editing.name}</h2></div><button type="button" onClick={()=>setEditing(null)} className="rounded-xl border px-3 py-2">Close</button></div>
      <form onSubmit={saveEdit} className="mt-5 grid gap-3 sm:grid-cols-2">
        <label className="text-sm font-semibold">Username<input className="mt-1 w-full rounded-xl border p-3 font-normal" value={editForm.data.username} onChange={e=>editForm.setData('username',e.target.value)} /></label>
        <div className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Name, email, phone, date of birth and other sensitive profile details can only be changed through the user-request approval workflow.</div>
        <label className="text-sm font-semibold">Role<select className="mt-1 w-full rounded-xl border p-3 font-normal" value={editForm.data.role} onChange={e=>editForm.setData('role',e.target.value)}>{['ADMIN','STAFF','SUPPORT','USER'].map(role=><option key={role}>{role}</option>)}</select></label>
        <label className="text-sm font-semibold">Status<select className="mt-1 w-full rounded-xl border p-3 font-normal" value={editForm.data.status} onChange={e=>editForm.setData('status',e.target.value)}>{['active','suspended','disabled'].map(status=><option key={status}>{status}</option>)}</select></label>
        <label className="text-sm font-semibold">Account tier<select className="mt-1 w-full rounded-xl border p-3 font-normal" value={editForm.data.tier} onChange={e=>editForm.setData('tier',Number(e.target.value))}>{tiers.map(tier=><option key={tier.id} value={tier.id}>{tier.name}</option>)}</select></label>
        <label className="text-sm font-semibold">New password (optional)<input type="password" className="mt-1 w-full rounded-xl border p-3 font-normal" placeholder="Leave blank to keep current" value={editForm.data.password} onChange={e=>editForm.setData('password',e.target.value)} /></label>
        <div className="sm:col-span-2 rounded-2xl bg-amber-50 p-4 text-xs text-amber-800">Password can be reset by an administrator here. Sensitive profile changes require the user to submit a request with a reason, then an administrator must approve it.</div>
        <button disabled={editForm.processing} className="sm:col-span-2 rounded-xl bg-slate-900 p-3 font-bold text-white disabled:opacity-50">{editForm.processing ? 'Saving…' : 'Save account changes'}</button>
      </form>
    </div></div>}

    {permissionUser && <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4"><div className="mx-auto mt-8 max-w-3xl rounded-3xl bg-white p-6 shadow-2xl">
      <div className="flex items-start justify-between"><div><p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Admin permission manager</p><h2 className="mt-1 text-2xl font-black">@{permissionUser.username}</h2><p className="mt-1 text-sm text-slate-500">Grant or revoke individual capabilities without changing the user's role.</p></div><button type="button" onClick={()=>setPermissionUser(null)} className="rounded-xl border px-3 py-2">Close</button></div>
      <form onSubmit={savePermissions} className="mt-5 grid gap-2 sm:grid-cols-2">
        {permissions.map(permission => <label key={permission} className="flex items-center gap-3 rounded-2xl border border-slate-200 p-3"><input type="checkbox" checked={permissionState[permission] === true} onChange={e=>setPermissionState({...permissionState,[permission]:e.target.checked})} /><span className="text-sm font-semibold">{permission}</span></label>)}
        <div className="sm:col-span-2 mt-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-600">Unchecked permissions are explicitly denied for this user when saved. This lets an administrator override the default role capabilities.</div>
        <button className="sm:col-span-2 rounded-xl bg-indigo-600 p-3 font-bold text-white">Save permissions</button>
      </form>
    </div></div>}

    {funding && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"><form onSubmit={submitFunding} className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
      <p className="text-xs font-bold uppercase tracking-wider text-emerald-600">Admin wallet funding</p><h2 className="mt-1 text-2xl font-black">Fund @{funding.username}</h2><p className="mt-1 text-sm text-slate-500">Funding is recorded as an immutable wallet movement and audited.</p>
      <label className="mt-5 block text-sm font-semibold">Amount (NGN)<input inputMode="decimal" className="mt-1 w-full rounded-xl border p-3 text-lg font-bold" placeholder="0.00" value={fundForm.data.amount} onChange={e=>fundForm.setData('amount',e.target.value)} /></label>
      <label className="mt-3 block text-sm font-semibold">Admin note (optional)<textarea className="mt-1 w-full rounded-xl border p-3 font-normal" maxLength={255} value={fundForm.data.note} onChange={e=>fundForm.setData('note',e.target.value)} /></label>
      <div className="mt-5 grid grid-cols-2 gap-2"><button type="button" onClick={()=>setFunding(null)} className="rounded-xl border p-3 font-bold">Cancel</button><button disabled={fundForm.processing} className="rounded-xl bg-emerald-600 p-3 font-bold text-white disabled:opacity-50">{fundForm.processing ? 'Funding…' : 'Fund user'}</button></div>
    </form></div>}

    {debiting && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"><form onSubmit={submitDebit} className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl"><p className="text-xs font-bold uppercase tracking-wider text-amber-600">Admin wallet debit</p><h2 className="mt-1 text-2xl font-black">Debit @{debiting.username}</h2><p className="mt-1 text-sm text-slate-500">Debit cannot exceed the available wallet balance. The movement and note are permanently audited.</p><label className="mt-5 block text-sm font-semibold">Amount (NGN)<input inputMode="decimal" className="mt-1 w-full rounded-xl border p-3 text-lg font-bold" placeholder="0.00" value={debitForm.data.amount} onChange={e=>debitForm.setData('amount',e.target.value)} /></label><label className="mt-3 block text-sm font-semibold">Admin note<textarea className="mt-1 w-full rounded-xl border p-3 font-normal" maxLength={255} value={debitForm.data.note} onChange={e=>debitForm.setData('note',e.target.value)} /></label><div className="mt-5 grid grid-cols-2 gap-2"><button type="button" onClick={()=>setDebiting(null)} className="rounded-xl border p-3 font-bold">Cancel</button><button disabled={debitForm.processing} className="rounded-xl bg-amber-500 p-3 font-bold text-white disabled:opacity-50">{debitForm.processing?'Debiting…':'Debit user'}</button></div></form></div>}
  </div></main></>;
}
