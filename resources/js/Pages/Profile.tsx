import { Head, router, useForm, usePage } from '@inertiajs/react';
import CoreMobileNav from '../Components/CoreMobileNav';

type SharedProps = { navigation?: { unreadNotifications?: number } };

type Props = {
  user: {
    name: string;
    username: string;
    email: string;
    phone: string;
    role: string;
    status: string;
    emailVerifiedAt: string | null;
    phoneVerifiedAt: string | null;
    referralCode: string;
    referralLink: string;
  };
};

export default function Profile({ user }: Props) {
  const unreadCount = usePage<SharedProps>().props.navigation?.unreadNotifications ?? 0;
  const form = useForm({ current_password: '', password: '', password_confirmation: '' });
  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.post('/profile/password', { preserveScroll: true, onSuccess: () => form.reset() });
  };

  const copy = (value: string) => { void navigator.clipboard?.writeText(value); };

  return <main className="min-h-screen bg-slate-50 p-4 pb-24 md:p-8">
    <Head title="Profile" />
    <div className="mx-auto max-w-3xl">
      <div className="mb-4 flex justify-end"><button type="button" onClick={() => router.post('/logout')} className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Logout</button></div>
      <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-sm font-semibold text-indigo-600">SEMIZZY ONE</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">Profile</h1>
        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <div><p className="text-xs font-semibold uppercase text-slate-500">Name</p><p className="mt-1 font-medium">{user.name}</p></div>
          <div><p className="text-xs font-semibold uppercase text-slate-500">Username</p><p className="mt-1 font-medium">@{user.username}</p></div>
          <div><p className="text-xs font-semibold uppercase text-slate-500">Email</p><p className="mt-1 break-all font-medium">{user.email}</p></div>
          <div><p className="text-xs font-semibold uppercase text-slate-500">Phone</p><p className="mt-1 font-medium">{user.phone || 'Not provided'}</p></div>
          <div><p className="text-xs font-semibold uppercase text-slate-500">Role</p><p className="mt-1 font-medium">{user.role}</p></div>
          <div><p className="text-xs font-semibold uppercase text-slate-500">Account status</p><p className="mt-1 font-medium">{user.status}</p></div>
        </div>
        <div className="mt-5 grid gap-2 text-sm"><p className="rounded-xl bg-slate-50 p-3">Email verification: <strong>{user.emailVerifiedAt ? 'Verified' : 'Not verified'}</strong></p><p className="rounded-xl bg-slate-50 p-3">Phone verification: <strong>{user.phoneVerifiedAt ? 'Verified' : 'Not verified'}</strong></p></div>
      </div>

      <section className="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Referral</p>
        <h2 className="mt-1 text-xl font-black">Invite friends and earn through referrals</h2>
        <p className="mt-1 text-sm text-slate-500">Share your code or link. Referral rewards can be configured by the platform later without changing your account identity.</p>
        <div className="mt-5 grid gap-3 sm:grid-cols-2">
          <div className="rounded-2xl bg-slate-50 p-4"><p className="text-xs font-semibold text-slate-500">Your referral code</p><div className="mt-2 flex items-center justify-between gap-3"><span className="font-black tracking-wider">{user.referralCode}</span><button type="button" onClick={() => copy(user.referralCode)} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow-sm">Copy</button></div></div>
          <div className="rounded-2xl bg-slate-50 p-4"><p className="text-xs font-semibold text-slate-500">Your referral link</p><div className="mt-2 flex items-center justify-between gap-3"><span className="truncate text-xs font-medium text-slate-700">{user.referralLink}</span><button type="button" onClick={() => copy(user.referralLink)} className="rounded-lg bg-white px-3 py-2 text-xs font-bold shadow-sm">Copy</button></div></div>
        </div>
      </section>

      <form onSubmit={submit} className="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-slate-900">Change password</h2><p className="mt-1 text-sm text-slate-600">Enter your current password before choosing a new password.</p>
        <div className="mt-5 space-y-3">
          <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="current-password" placeholder="Current password" value={form.data.current_password} onChange={e => form.setData('current_password', e.target.value)} />
          {form.errors.current_password && <p className="text-sm text-red-600">{form.errors.current_password}</p>}
          <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="New password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} />
          {form.errors.password && <p className="text-sm text-red-600">{form.errors.password}</p>}
          <input className="w-full rounded-xl border border-slate-300 p-3" type="password" autoComplete="new-password" placeholder="Confirm new password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} />
        </div>
        <button disabled={form.processing} className="mt-5 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white disabled:opacity-50">{form.processing ? 'Updating…' : 'Change password'}</button>
      </form>
      <CoreMobileNav active="profile" unreadCount={unreadCount} />
    </div>
  </main>;
}
