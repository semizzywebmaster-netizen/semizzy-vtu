import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Props = { hasPin: boolean; email: string };

export default function TransactionPin({ hasPin, email }: Props) {
  const [otpSent, setOtpSent] = useState(false);
  const [otpSending, setOtpSending] = useState(false);
  const [forgotPin, setForgotPin] = useState(false);
  const f = useForm({ current_pin: '', pin: '', pin_confirmation: '', otp_code: '', forgot_pin: false });

  const requestOtp = () => {
    setOtpSending(true);
    router.post('/security/otp/request', { purpose: 'transaction_pin_change' }, {
      preserveScroll: true,
      onSuccess: () => setOtpSent(true),
      onFinish: () => setOtpSending(false),
    });
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    f.setData('forgot_pin', forgotPin);
    f.post('/profile/transaction-pin', { preserveScroll: true, onSuccess: () => f.reset() });
  };

  return <main className="min-h-screen bg-slate-50 p-4 md:p-8">
    <Head title="Transaction PIN" />
    <div className="mx-auto max-w-lg rounded-3xl border bg-white p-6 shadow-sm">
      <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Security</p>
      <h1 className="mt-2 text-2xl font-black">{hasPin ? 'Change' : 'Set'} transaction PIN</h1>
      <p className="mt-2 text-sm text-slate-600">A one-time 6-digit OTP is required. If you forgot the current PIN, OTP recovery lets you set a new one without entering the old PIN.</p>
      <div className="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-600">OTP destination: <b>{email}</b></div>

      {hasPin && <label className="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
        <input type="checkbox" checked={forgotPin} onChange={e => { setForgotPin(e.target.checked); f.setData('forgot_pin', e.target.checked); }} className="mt-1" />
        <span><b>I forgot my current PIN</b><br /><span className="text-amber-800">Do not enter the old PIN. Email OTP will authorize the reset.</span></span>
      </label>}

      <button type="button" onClick={requestOtp} disabled={otpSending} className="mt-4 w-full rounded-xl border border-indigo-200 bg-indigo-50 p-3 font-bold text-indigo-700 disabled:opacity-50">
        {otpSending ? 'Sending OTP…' : otpSent ? 'Resend OTP' : 'Send OTP to my email'}
      </button>
      {otpSent && <p className="mt-2 text-sm font-semibold text-emerald-700">OTP sent. Check your email; the code expires in 10 minutes.</p>}

      <form onSubmit={submit} className="mt-6 space-y-4">
        {hasPin && !forgotPin && <input required inputMode="numeric" pattern="\d{4}" maxLength={4} type="password" className="w-full rounded-xl border p-3" placeholder="Current 4-digit PIN" value={f.data.current_pin} onChange={e => f.setData('current_pin', e.target.value.replace(/\D/g, '').slice(0, 4))} />}
        <input required inputMode="numeric" pattern="\d{4}" maxLength={4} type="password" className="w-full rounded-xl border p-3" placeholder="New 4-digit PIN" value={f.data.pin} onChange={e => f.setData('pin', e.target.value.replace(/\D/g, '').slice(0, 4))} />
        <input required inputMode="numeric" pattern="\d{4}" maxLength={4} type="password" className="w-full rounded-xl border p-3" placeholder="Confirm PIN" value={f.data.pin_confirmation} onChange={e => f.setData('pin_confirmation', e.target.value.replace(/\D/g, '').slice(0, 4))} />
        <input required inputMode="numeric" pattern="\d{6}" maxLength={6} type="text" autoComplete="one-time-code" className="w-full rounded-xl border p-3 tracking-[0.3em]" placeholder="6-digit OTP" value={f.data.otp_code} onChange={e => f.setData('otp_code', e.target.value.replace(/\D/g, '').slice(0, 6))} />
        {Object.values(f.errors).map((e, i) => <p key={i} className="text-sm text-red-600">{e}</p>)}
        <button disabled={f.processing || f.data.otp_code.length !== 6} className="w-full rounded-xl bg-indigo-600 p-3 font-bold text-white disabled:opacity-50">{f.processing ? 'Saving…' : hasPin ? 'Change PIN securely' : 'Set PIN securely'}</button>
      </form>
      <a href="/profile" className="mt-5 block text-sm font-semibold text-slate-600">← Back to profile</a>
    </div>
  </main>;
}