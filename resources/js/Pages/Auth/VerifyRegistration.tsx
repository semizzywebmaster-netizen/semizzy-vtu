import { Head, useForm } from '@inertiajs/react';

export default function VerifyRegistration({ channels = ['email'], selectedChannel = 'email', destination = '', expiryMinutes = 10, resendSeconds = 60 }: { channels?: string[]; selectedChannel?: string; destination?: string; expiryMinutes?: number; resendSeconds?: number }) {
  const form = useForm({ otp_code: '' });
  const resend = useForm({ channel: selectedChannel });
  const verify = (e: React.FormEvent) => { e.preventDefault(); form.post('/register/verify'); };
  return <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
    <Head title="Verify registration" />
    <div className="w-full max-w-md rounded-3xl bg-white p-7 shadow-xl">
      <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">Registration verification</p>
      <h1 className="mt-2 text-3xl font-black">Verify your account</h1>
      <p className="mt-2 text-sm text-slate-600">We sent a 6-digit code to <span className="font-bold">{destination}</span>. The code expires in {expiryMinutes} minutes.</p>
      <form onSubmit={verify} className="mt-6">
        <input autoFocus inputMode="numeric" autoComplete="one-time-code" maxLength={6} className="w-full rounded-xl border p-4 text-center text-2xl font-black tracking-[0.5em]" placeholder="000000" value={form.data.otp_code} onChange={e=>form.setData('otp_code',e.target.value.replace(/\D/g,'').slice(0,6))} />
        {form.errors.otp_code && <p className="mt-2 text-sm text-red-600">{form.errors.otp_code}</p>}
        <button disabled={form.processing} className="mt-4 w-full rounded-xl bg-slate-900 p-3 font-bold text-white disabled:opacity-50">{form.processing?'Verifying…':'Verify registration'}</button>
      </form>
      {channels.length > 1 && <div className="mt-6 border-t pt-5"><p className="text-sm font-bold">Need another code?</p><div className="mt-2 grid gap-2">{channels.map(channel=><button key={channel} disabled={resend.processing} onClick={()=>{resend.setData('channel',channel); resend.post('/register/verify/send',{preserveScroll:true});}} className="rounded-xl border p-3 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50">{channel==='email'?'Send by email':channel==='sms'?'Send by SMS':'Send by WhatsApp'}</button>)}</div><p className="mt-2 text-xs text-slate-500">Please wait at least {resendSeconds} seconds between resend requests.</p></div>}
    </div>
  </main>;
}
