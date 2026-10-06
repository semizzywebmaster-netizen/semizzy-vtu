import { Head, useForm } from '@inertiajs/react';

type Settings = {
  platform_name: string;
  support_email: string;
  support_notice: string;
  default_timezone: string;
  theme_key: string;
  theme_primary: string;
};

type Props = { settings: Settings };

const themes = [
  { key: 'ocean-blue', name: 'Ocean Blue', color: '#2563EB', description: 'Clean fintech blue' },
  { key: 'emerald', name: 'Emerald', color: '#059669', description: 'Fresh professional green' },
  { key: 'royal-purple', name: 'Royal Purple', color: '#7C3AED', description: 'Premium modern purple' },
  { key: 'crimson', name: 'Crimson', color: '#DC2626', description: 'Bold energetic red' },
  { key: 'sunset-orange', name: 'Sunset Orange', color: '#EA580C', description: 'Warm vibrant orange' },
];

export default function SettingsPage({ settings }: Props) {
  const initialPreset = themes.some(t => t.key === settings.theme_key) ? settings.theme_key : 'custom';
  const form = useForm({
    platform_name: settings.platform_name,
    support_email: settings.support_email,
    support_notice: settings.support_notice,
    default_timezone: settings.default_timezone,
    theme_key: initialPreset,
    theme_primary: settings.theme_primary || '#2563EB',
  });

  const chooseTheme = (theme: typeof themes[number]) => {
    form.setData(data => ({ ...data, theme_key: theme.key, theme_primary: theme.color }));
  };

  const chooseCustom = (color: string) => {
    form.setData(data => ({ ...data, theme_key: 'custom', theme_primary: color }));
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    form.put('/admin/settings', { preserveScroll: true });
  };

  return <><Head title="System settings" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-4xl">
    <header>
      <p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADMIN</p>
      <h1 className="mt-1 text-2xl font-extrabold text-slate-900">System settings</h1>
      <p className="mt-2 text-sm text-slate-600">Control the website identity, support defaults and the global website colour. The selected colour is applied across the public site, user area and admin interface.</p>
    </header>

    <form onSubmit={submit} className="mt-6 space-y-6">
      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-4">
          <h2 className="text-lg font-extrabold text-slate-900">Website colour</h2>
          <p className="mt-1 text-sm text-slate-500">Choose a ready-made brand palette or use any custom colour.</p>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {themes.map(theme => {
            const active = form.data.theme_key === theme.key && form.data.theme_primary.toLowerCase() === theme.color.toLowerCase();
            return <button type="button" key={theme.key} onClick={() => chooseTheme(theme)}
              className={'rounded-2xl border-2 p-4 text-left transition ' + (active ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200 hover:border-slate-300')}>
              <div className="flex items-center gap-3">
                <span className="h-11 w-11 rounded-xl shadow-sm" style={{ backgroundColor: theme.color }} />
                <span>
                  <span className="block font-bold text-slate-900">{theme.name}</span>
                  <span className="block text-xs text-slate-500">{theme.description}</span>
                </span>
              </div>
              <span className="mt-3 block text-xs font-mono text-slate-500">{theme.color}</span>
            </button>;
          })}

          <label className={'rounded-2xl border-2 p-4 cursor-pointer transition ' + (form.data.theme_key === 'custom' ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200')}>
            <div className="flex items-center gap-3">
              <input type="color" value={form.data.theme_primary} onChange={e => chooseCustom(e.target.value)}
                className="h-11 w-14 cursor-pointer rounded-lg border-0 bg-transparent p-0" aria-label="Choose custom website colour" />
              <span>
                <span className="block font-bold text-slate-900">Custom Colour</span>
                <span className="block text-xs text-slate-500">Pick your exact brand colour</span>
              </span>
            </div>
            <input type="text" value={form.data.theme_primary} onChange={e => chooseCustom(e.target.value)}
              pattern="#[0-9A-Fa-f]{6}" maxLength={7} className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" aria-label="Custom colour hex value" />
          </label>
        </div>

        <div className="mt-5 flex items-center gap-4 rounded-xl border border-slate-200 p-4">
          <span className="h-12 w-12 rounded-xl" style={{ backgroundColor: form.data.theme_primary }} />
          <div><p className="font-bold text-slate-900">Selected colour</p><p className="font-mono text-sm text-slate-500">{form.data.theme_primary}</p></div>
        </div>
        {form.errors.theme_primary && <p className="mt-2 text-sm text-red-600">{form.errors.theme_primary}</p>}
      </section>

      <section className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label className="block"><span className="text-sm font-semibold text-slate-800">Platform name</span><input className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.platform_name} onChange={e=>form.setData('platform_name',e.target.value)} maxLength={80} required />{form.errors.platform_name&&<span className="mt-1 block text-sm text-red-600">{form.errors.platform_name}</span>}</label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support email</span><input type="email" className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.support_email} onChange={e=>form.setData('support_email',e.target.value)} maxLength={254} />{form.errors.support_email&&<span className="mt-1 block text-sm text-red-600">{form.errors.support_email}</span>}</label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support notice</span><textarea className="mt-1 min-h-24 w-full rounded-xl border border-slate-300 p-3" value={form.data.support_notice} onChange={e=>form.setData('support_notice',e.target.value)} maxLength={500} placeholder="Optional public support notice" />{form.errors.support_notice&&<span className="mt-1 block text-sm text-red-600">{form.errors.support_notice}</span>}</label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Default timezone</span><input className="mt-1 w-full rounded-xl border border-slate-300 p-3" value={form.data.default_timezone} onChange={e=>form.setData('default_timezone',e.target.value)} placeholder="Africa/Lagos" required />{form.errors.default_timezone&&<span className="mt-1 block text-sm text-red-600">{form.errors.default_timezone}</span>}<span className="mt-1 block text-xs text-slate-500">Use a valid IANA timezone, for example Africa/Lagos or UTC.</span></label>
      </section>

      <div className="flex flex-wrap items-center gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing?'Saving…':'Save settings'}</button>{form.recentlySuccessful&&<span className="text-sm text-emerald-700">Saved. The new website colour is now active.</span>}</div>
    </form>
    <nav className="mt-6 flex flex-wrap gap-4 text-sm font-semibold text-indigo-700"><a href="/dashboard">Dashboard</a><a href="/admin/health">System health</a><a href="/admin/audit-events">Audit events</a></nav>
  </div></main></>;
}
