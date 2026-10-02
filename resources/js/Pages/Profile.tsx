import { Head, useForm, usePage } from '@inertiajs/react';
import CoreMobileNav from '../Components/CoreMobileNav';

type SharedProps = { navigation?: { unreadNotifications?: number } };

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
  const unreadCount = usePage<SharedProps>().props.navigation?.unreadNotifications ?? 0;
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

        <CoreMobileNav active="profile" unreadCount={unreadCount} />
      </div>
    </main>
  );
}
