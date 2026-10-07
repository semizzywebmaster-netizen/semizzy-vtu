import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ platform, flash }: { platform?: { platform_name?: string }; flash?: { deviceVerificationRequired?: boolean } }) {
  const form = useForm({ login: '', password: '', remember: false, otp_code: '' });

  return <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
    <Head title={`Sign in · ${platform?.platform_name || 'SEMIZZY ONE'}`} />
    <form onSubmit={e => { e.preventDefault(); form.post('/login'); }} className="w-full max-w-md rounded-3xl bg-white p-7 shadow-xl">
      <h1 className="mt-1 text-3xl font-black">Sign in</h1>
      <p className="mt-2 text-sm text-slate-500">Enter your account credentials to continue.</p>
      <input autoComplete="username" className="mt-6 w-full rounded-xl border p-3" type="text" placeholder="Username, email or phone" value={form.data.login} onChange={e => form.setData('login', e.target.value)} />
      {form.errors.login && <p className="mt-1 text-sm text-red-600">{form.errors.login}</p>}
      {flash?.deviceVerificationRequired && <div className="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">New device verification required. Check your email for the 6-digit code.</div>}\n      {flash?.deviceVerificationRequired && <input inputMode="numeric" autoComplete="one-time-code" className="mt-3 w-full rounded-xl border p-3" type="text" maxLength={6} placeholder="6-digit verification code" value={form.data.otp_code} onChange={e => form.setData('otp_code', e.target.value.replace(/\\D/g, ''))} />}\n      {form.errors.otp_code && <p className="mt-1 text-sm text-red-600">{form.errors.otp_code}</p>}\n      <input autoComplete="current-password" className="mt-3 w-full rounded-xl border p-3" type="password" placeholder="Password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} />
      {form.errors.password && <p className="mt-1 text-sm text-red-600">{form.errors.password}</p>}
      <label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.remember} onChange={e => form.setData('remember', e.target.checked)} />Remember me</label>
      <div className="mt-2 flex justify-between text-sm"><Link href="/forgot-password" className="font-medium text-slate-700 hover:underline">Forgot password?</Link><Link href="/register" className="font-medium text-indigo-700 hover:underline">Create account</Link></div>
      <button disabled={form.processing} className="mt-5 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white disabled:opacity-50">{form.processing ? 'Signing in…' : 'Sign in'}</button>
    </form>
  </main>;
}
