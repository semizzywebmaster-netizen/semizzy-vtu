import { Head, useForm } from '@inertiajs/react';

type Props = {
  user: {
    name: string;
    email: string;
    role: string;
    status: string;
    emailVerifiedAt: string | null;
  };
};

export default function Profile({ user }: Props) {
  const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post('/profile/password', {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    });
  };

  return (
    <main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8">
      <Head title="Profile" />
      <div className="mx-auto max-w-3xl">
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p className="text-sm font-semibold text-slate-500">SEMIZZY ONE</p>
          <h1 className="mt-1 text-2xl font-extrabold text-slate-900">Profile</h1>
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            <div><p className="text-xs font-semibold uppercase text-slate-500">Name</p><p className="mt-1 font-medium">{user.name}</p></div>
            <div><p className="text-xs font-semibold uppercase text-slate-500">Email</p><p className="mt-1 break-all font-medium">{user.email}</p></div>
            <div><p className="text-xs font-semibold uppercase text-slate-500">Role</p><p className="mt-1 font-medium">{user.role}</p></div>
            <div><p className="text-xs font-semibold uppercase text-slate-500">Account status</p><p className="mt-1 font-medium">{user.status}</p></div>
          </div>
          <p className="mt-5 text-sm text-slate-600">
            Email verification: {user.emailVerifiedAt ? 'Verified' : 'Not verified'}
          </p>
        </div>

        <form onSubmit={submit} className="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-bold text-slate-900">Change password</h2>
          <p className="mt-1 text-sm text-slate-600">Enter your current password before choosing a new password.</p>
          <div className="mt-5 space-y-3">
            <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="current-password" placeholder="Current password" value={form.data.current_password} onChange={e => form.setData('current_password', e.target.value)} />
            {form.errors.current_password && <p className="text-sm text-red-600">{form.errors.current_password}</p>}
            <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="New password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} />
            {form.errors.password && <p className="text-sm text-red-600">{form.errors.password}</p>}
            <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="Confirm new password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} />
          </div>
          <button disabled={form.processing} className="mt-5 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white disabled:opacity-50">
            {form.processing ? 'Updating…' : 'Change password'}
          </button>
        </form>

        <nav className="fixed inset-x-0 bottom-0 border-t border-slate-200 bg-white/95 p-2 backdrop-blur md:static md:mt-6 md:border-0 md:bg-transparent md:p-0">
          <div className="mx-auto flex max-w-3xl justify-around text-xs font-semibold text-slate-600 md:justify-start md:gap-5">
            <a href="/dashboard" className="p-2">Home</a>
            <a href="/dashboard" className="p-2">Services</a>
            <a href="/dashboard" className="p-2">Transactions</a>
            <a href="/dashboard" className="p-2">Notifications</a>
            <a href="/profile" className="p-2 text-slate-900">Profile</a>
          </div>
        </nav>
      </div>
    </main>
  );
}
