import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ForgotPassword({ platform }: { platform?: { platform_name?: string } }) {
  const [otpSent, setOtpSent] = useState(false);
  const form = useForm({ email: '', otp_channel: 'email', otp_code: '', password: '', password_confirmation: '' });

  const requestOtp = () => {
    form.post('/forgot-password/otp', {
      preserveScroll: true,
      onSuccess: () => setOtpSent(true),
    });
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/forgot-password/reset', { preserveScroll: true });
  };

  return <main className="mx-auto flex min-h-screen max-w-md items-center px-6 py-10">
    <Head title={`Forgot password · ${platform?.platform_name || 'SEMIZZY ONE'}`} />
    <section className="w-full space-y-6 rounded-2xl border bg-white p-6 shadow-sm">
      <div><h1 className="text-2xl font-semibold">Reset your password</h1><p className="mt-2 text-sm text-slate-600">We will send a one-time verification code to your registered email. No old password is required.</p></div>
      <input type="email" required autoComplete="email" className="w-full rounded-lg border px-3 py-2" placeholder="Email address" value={form.data.email} onChange={e=>form.setData('email',e.target.value)} />
      <select className="w-full rounded-lg border px-3 py-2" value={form.data.otp_channel} onChange={e=>form.setData("otp_channel",e.target.value)}><option value="email">Email OTP</option><option value="sms">SMS OTP</option><option value="whatsapp">WhatsApp OTP (verified WhatsApp only)</option></select><button type="button" onClick={requestOtp} disabled={form.processing} className="w-full rounded-lg bg-slate-900 px-4 py-2 text-white">{otpSent ? 'Resend OTP' : 'Send OTP'}</button>
      {otpSent && <form onSubmit={submit} className="space-y-4">
        <input inputMode="numeric" pattern="\d{6}" maxLength={6} autoComplete="one-time-code" className="w-full rounded-lg border px-3 py-2 tracking-widest" placeholder="6-digit OTP" value={form.data.otp_code} onChange={e=>form.setData('otp_code',e.target.value.replace(/\D/g,'').slice(0,6))} />
        <input type="password" required autoComplete="new-password" className="w-full rounded-lg border px-3 py-2" placeholder="New password (12+ chars, upper/lower/number/symbol)" value={form.data.password} onChange={e=>form.setData('password',e.target.value)} />
        <input type="password" required autoComplete="new-password" className="w-full rounded-lg border px-3 py-2" placeholder="Confirm new password" value={form.data.password_confirmation} onChange={e=>form.setData('password_confirmation',e.target.value)} />
        {Object.values(form.errors).map((error,i)=><p key={i} className="text-sm text-red-600">{error}</p>)}
        <button disabled={form.processing || form.data.otp_code.length !== 6} className="w-full rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white">{form.processing ? 'Resetting…' : 'Reset password'}</button>
      </form>}
      <a href="/login" className="block text-sm text-slate-600 hover:underline">← Back to login</a>
    </section>
  </main>;
}