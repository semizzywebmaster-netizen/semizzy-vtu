import { Head, useForm } from '@inertiajs/react';

export default function VerifyEmail({ platform }: { platform?: { platform_name?: string } }) {
 const form=useForm({});
 return <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6"><Head title={`Verify email · ${platform?.platform_name || 'SEMIZZY ONE'}`}/><section className="w-full max-w-md rounded-2xl bg-white p-7 shadow"><h1 className="text-2xl font-bold">Verify your email</h1><p className="mt-3 text-slate-600">Check your inbox for the verification link.</p><button onClick={()=>form.post('/email/verification-notification')} disabled={form.processing} className="mt-6 w-full rounded-xl bg-slate-900 p-3 font-semibold text-white">Resend verification email</button></section></main>;
}
