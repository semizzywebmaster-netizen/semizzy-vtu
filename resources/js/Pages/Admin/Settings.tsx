import { Head, useForm } from '@inertiajs/react';

type Settings = { platform_name: string; support_email: string; support_notice: string; default_timezone: string };
type Props = { settings: Settings };

export default function SettingsPage({ settings }: Props) {
  const form = useForm({
    platform_name: settings.platform_name,
    support_email: settings.support_email,
    support_notice: settings.support_notice,
    default_timezone: settings.default_timezone,
  });
  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.put('/admin/settings', { preserveScroll: true });
  };

  return <><Head title="System settings" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-3xl">
    <header><p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">System settings</h1><p className="mt-2 text-sm text-slate-600">Manage the Core's public identity and support defaults. Provider credentials, application keys, and other secrets are not editable here.</p></header>
    <form onSubmit={submit} className="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <label className="block"><span className="text-sm font-semibold text-slate-800">Platform name</span><input className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.platform_name} onChange={e=>form.setData('platform_name',e.target.value)} maxLength={80} required />{form.errors.platform_name&&<span className="mt-1 block text-sm text-red-600">{form.errors.platform_name}</span>}</label>
      <label className="block"><span className="text-sm font-semibold text-slate-800">Support email</span><input type="email" className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.support_email} onChange={e=>form.setData('support_email',e.target.value)} maxLength={254} />{form.errors.support_email&&<span className="mt-1 block text-sm text-red-600">{form.errors.support_email}</span>}</label>
      <label className="block"><span className="text-sm font-semibold text-slate-800">Support notice</span><textarea className="mt-1 min-h-24 w-full rounded-xl border border-slate-300 p-3" value={form.data.support_notice} onChange={e=>form.setData('support_notice',e.target.value)} maxLength={500} placeholder="Optional public support notice" />{form.errors.support_notice&&<span className="mt-1 block text-sm text-red-600">{form.errors.support_notice}</span>}</label>
      <label className="block"><span className="text-sm font-semibold text-slate-800">Default timezone</span><input className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.default_timezone} onChange={e=>form.setData('default_timezone',e.target.value)} placeholder="Africa/Lagos" required />{form.errors.default_timezone&&<span className="mt-1 block text-sm text-red-600">{form.errors.default_timezone}</span>}<span className="mt-1 block text-xs text-slate-500">Use a valid IANA timezone, for example Africa/Lagos or UTC.</span></label>
      <div className="flex flex-wrap items-center gap-3"><button disabled={form.processing} className="rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing?'Saving…':'Save settings'}</button>{form.recentlySuccessful&&<span className="text-sm text-emerald-700">Saved.</span>}</div>
    </form>
    <nav className="mt-6 flex flex-wrap gap-4 text-sm font-semibold text-indigo-700"><a href="/dashboard">Dashboard</a><a href="/admin/health">System health</a><a href="/admin/audit-events">Audit events</a></nav>
  </div></main></>;
}
