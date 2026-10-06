import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';

export default function ResetPassword({ platform }: { platform?: { platform_name?: string } }) {
  return <main className="mx-auto flex min-h-screen max-w-md items-center px-6 py-10">
    <Head title="Set new password" />
    <section className="w-full rounded-2xl border bg-white p-6 shadow-sm">
      <h1 className="text-2xl font-semibold">Password recovery moved</h1>
      <p className="mt-2 text-sm text-slate-600">Use the email OTP recovery flow to set a new password without your old password.</p>
      <Link href="/forgot-password" className="mt-5 inline-block rounded-lg bg-slate-900 px-4 py-2 text-white">Start password recovery</Link>
    </section>
  </main>;
}