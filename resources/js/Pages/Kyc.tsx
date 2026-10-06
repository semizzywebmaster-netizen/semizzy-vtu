import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Props = { kyc: any };

export default function Kyc({ kyc }: Props) {
  const [channel, setChannel] = useState('email');
  const otp = useForm({ channel });
  const verify = useForm({ code: '' });
  const form = useForm({
    identity_type: kyc.identityType || 'nin',
    identity_number: '',
    identity_document: null as File | null,
    transaction_pin: '',
  });

  const sendOtp = (e: React.FormEvent) => {
    e.preventDefault();
    otp.setData('channel', channel);
    otp.post('/kyc/otp', { preserveScroll: true });
  };

  const verifyOtp = (e: React.FormEvent) => {
    e.preventDefault();
    verify.post('/kyc/otp/verify', { preserveScroll: true });
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post('/kyc', { forceFormData: true, preserveScroll: true });
  };

  return (
    <>
      <Head title="KYC Verification" />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-3xl">
          <div className="rounded-3xl border bg-white p-6 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-wider text-indigo-600">SEMIZZY ONE · KYC</p>
            <h1 className="mt-2 text-3xl font-black">Identity verification</h1>
            <p className="mt-2 text-sm text-slate-600">Verify a trusted contact, then submit your identity details and document for secure review.</p>

            <section className="mt-6 rounded-2xl border bg-slate-50 p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="font-bold">Contact verification</span>
                <span className={kyc.otpVerified ? 'rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-700' : 'rounded-full bg-amber-50 px-3 py-1 text-sm font-bold text-amber-700'}>
                  {kyc.otpVerified ? 'Verified' : 'Required'}
                </span>
              </div>
              {!kyc.otpVerified && (
                <>
                  <form onSubmit={sendOtp} className="mt-4 flex flex-col gap-3 sm:flex-row">
                    <select className="rounded-xl border p-3" value={channel} onChange={e => setChannel(e.target.value)}>
                      <option value="email">Email OTP</option>
                      <option value="sms">SMS OTP</option>
                      <option value="whatsapp">WhatsApp OTP</option>
                    </select>
                    <button disabled={otp.processing} className="rounded-xl bg-slate-900 px-4 py-3 font-bold text-white disabled:opacity-50">
                      {otp.processing ? 'Sending…' : 'Send code'}
                    </button>
                  </form>
                  <form onSubmit={verifyOtp} className="mt-3 flex flex-col gap-3 sm:flex-row">
                    <input required inputMode="numeric" pattern="\d{6}" maxLength={6} className="rounded-xl border p-3" placeholder="6-digit OTP" value={verify.data.code} onChange={e => verify.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))} />
                    <button disabled={verify.processing} className="rounded-xl border px-4 py-3 font-bold disabled:opacity-50">
                      {verify.processing ? 'Checking…' : 'Verify OTP'}
                    </button>
                  </form>
                </>
              )}
            </section>

            <div className="mt-5 rounded-2xl bg-slate-50 p-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="font-bold">Application status</span>
                <span className="rounded-full bg-indigo-50 px-3 py-1 text-sm font-bold text-indigo-700">{String(kyc.status).replace('_', ' ')}</span>
              </div>
              {kyc.identityNumber && <p className="mt-2 text-sm">Identity: <b>{kyc.identityNumber}</b></p>}
              {kyc.rejectionReason && <p className="mt-2 text-sm text-red-700">Reason: {kyc.rejectionReason}</p>}
            </div>

            {kyc.status !== 'approved' && (
              <form onSubmit={submit} className="mt-6 space-y-4">
                <label className="block text-sm font-bold">Identity type
                  <select className="mt-1 w-full rounded-xl border p-3 font-normal" value={form.data.identity_type} onChange={e => form.setData('identity_type', e.target.value)}>
                    <option value="nin">NIN</option>
                    <option value="bvn">BVN</option>
                    <option value="passport">International Passport</option>
                    <option value="drivers_license">Driver's Licence</option>
                  </select>
                </label>
                <label className="block text-sm font-bold">Identity number
                  <input className="mt-1 w-full rounded-xl border p-3 font-normal disabled:bg-slate-100" disabled={!!kyc.identityNumber} value={form.data.identity_number} onChange={e => form.setData('identity_number', e.target.value)} placeholder={kyc.identityNumber ? 'Locked after saving' : 'Enter identity number'} />
                </label>
                <label className="block text-sm font-bold">Identity document
                  <input type="file" accept="image/*,.pdf" className="mt-1 w-full rounded-xl border p-3 font-normal" onChange={e => form.setData('identity_document', e.target.files?.[0] || null)} />
                </label>
                <input required inputMode="numeric" pattern="\d{4}" maxLength={4} type="password" className="w-full rounded-xl border p-3" placeholder="Transaction PIN" value={form.data.transaction_pin} onChange={e => form.setData('transaction_pin', e.target.value.replace(/\D/g, '').slice(0, 4))} />
                <button disabled={form.processing || !kyc.otpVerified} className="w-full rounded-xl bg-indigo-600 p-3 font-bold text-white disabled:opacity-50">
                  {form.processing ? 'Submitting…' : kyc.otpVerified ? 'Submit KYC application' : 'Verify OTP to continue'}
                </button>
              </form>
            )}

            {kyc.documentUrl && <a href={kyc.documentUrl} className="mt-5 inline-block font-bold text-indigo-700">View submitted document</a>}
            <a href="/profile" className="mt-6 block text-sm font-semibold text-slate-600">← Back to profile</a>
          </div>
        </div>
      </main>
    </>
  );
}