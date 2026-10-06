import { Head, useForm } from '@inertiajs/react';
import { THEMES, DEFAULT_CUSTOM, type Palette, type Skin } from '../../Utils/ThemeSystem';

type Props = { settings: { platform_name:string; support_email:string; support_notice:string; default_timezone:string; theme_key:string; theme_primary:string; skin_default:Skin; theme_custom_light:Partial<Palette>; theme_custom_dark:Partial<Palette>; } };
const fields: {key:keyof Palette; label:string}[] = [
  {key:'primary',label:'Primary'},{key:'secondary',label:'Secondary'},{key:'accent',label:'Accent'},{key:'background',label:'Background'},
  {key:'surface',label:'Surface / Cards'},{key:'text',label:'Text'},{key:'muted',label:'Muted Text'},{key:'border',label:'Border'},
  {key:'success',label:'Success'},{key:'warning',label:'Warning'},{key:'danger',label:'Danger'},
];
const validHex=(v:string)=>/^#[0-9A-Fa-f]{6}$/.test(v);

function CustomBuilder({skin,value,onChange}:{skin:Skin;value:Partial<Palette>;onChange:(key:keyof Palette,value:string)=>void}){
  const fallback=DEFAULT_CUSTOM[skin];
  return <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-5">
    <div className="mb-4"><h3 className="font-extrabold text-slate-900">Custom {skin==='light'?'Light':'Dark'} palette</h3><p className="mt-1 text-sm text-slate-500">Choose the colour tokens used globally by SEMIZZY ONE. HEX values are validated before publishing.</p></div>
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {fields.map(field=>{const current=(value[field.key] as string)||fallback[field.key];return <label key={String(field.key)} className="rounded-xl border border-slate-200 bg-white p-3">
        <span className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">{field.label}</span>
        <div className="flex items-center gap-2"><input type="color" value={validHex(current)?current:fallback[field.key]} onChange={e=>onChange(field.key,e.target.value)} className="h-10 w-12 cursor-pointer rounded-lg border-0 bg-transparent p-0" />
        <input value={current} onChange={e=>onChange(field.key,e.target.value)} pattern="#[0-9A-Fa-f]{6}" maxLength={7} className="min-w-0 flex-1 rounded-lg border border-slate-300 px-2.5 py-2 font-mono text-sm" /></div>
      </label>})}
    </div>
  </div>;
}

export default function SettingsPage({settings}:Props){
  const form=useForm({
    platform_name:settings.platform_name,support_email:settings.support_email,support_notice:settings.support_notice,default_timezone:settings.default_timezone,
    theme_key:settings.theme_key||'modern-corporate',theme_primary:settings.theme_primary||'#2563EB',skin_default:settings.skin_default||('light' as Skin),
    theme_custom_light:settings.theme_custom_light||{},theme_custom_dark:settings.theme_custom_dark||{},
  });
  const choose=(key:string)=>{const theme=THEMES.find(t=>t.key===key);if(theme)form.setData(d=>({...d,theme_key:key,theme_primary:theme.light.primary}));};
  const updateCustom=(skin:Skin,key:keyof Palette,value:string)=>{
    form.setData(d=>({...d,[skin==='light'?'theme_custom_light':'theme_custom_dark']:{...(skin==='light'?d.theme_custom_light:d.theme_custom_dark),[key]:value},theme_primary:key==='primary'?value:d.theme_primary}));
  };
  const submit=(e:React.FormEvent)=>{e.preventDefault();form.put('/admin/settings',{preserveScroll:true});};
  return <><Head title="System settings" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <header><p className="text-sm font-semibold text-indigo-700">SEMIZZY ONE · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">System settings</h1><p className="mt-2 max-w-3xl text-sm text-slate-600">Configure the global professional design system. Preset themes are original design directions inspired by leading fintech/SaaS patterns, not interface copies. Skin has exactly two options: Light and Dark.</p></header>
    <form onSubmit={submit} className="mt-6 space-y-6">
      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-5"><h2 className="text-lg font-extrabold text-slate-900">Global Theme · 11 options</h2><p className="mt-1 text-sm text-slate-500">Choose one theme for the whole website. It is applied to public pages, user dashboards, admin pages and mobile/PWA surfaces.</p></div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {THEMES.map(theme=><button type="button" key={theme.key} onClick={()=>choose(theme.key)} className={'rounded-2xl border-2 p-4 text-left transition '+(form.data.theme_key===theme.key?'border-indigo-600 bg-indigo-50':'border-slate-200 hover:border-slate-300')}>
            <div className="flex items-start gap-3"><span className="h-12 w-12 shrink-0 rounded-xl shadow-sm" style={{background:'linear-gradient(135deg, '+theme.light.primary+', '+theme.light.accent+')'}} /><span><span className="block font-extrabold text-slate-900">{theme.name}</span><span className="mt-1 block text-xs leading-5 text-slate-500">{theme.description}</span></span></div>
            <div className="mt-4 flex gap-1.5">{[theme.light.primary,theme.light.secondary,theme.light.accent,theme.light.surface].map(c=><i key={c} className="h-5 w-5 rounded-full border border-slate-200" style={{backgroundColor:c}} />)}</div>
          </button>)}
          <button type="button" onClick={()=>choose('custom')} className={'rounded-2xl border-2 p-4 text-left transition '+(form.data.theme_key==='custom'?'border-indigo-600 bg-indigo-50':'border-slate-200 hover:border-slate-300')}>
            <div className="flex items-start gap-3"><span className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-slate-900 via-slate-500 to-white text-lg shadow-sm">🎨</span><span><span className="block font-extrabold text-slate-900">Custom Theme</span><span className="mt-1 block text-xs leading-5 text-slate-500">Admin-configurable colours with live colour fields for Light and Dark.</span></span></div>
          </button>
        </div>
        {form.data.theme_key==='custom' && <><CustomBuilder skin="light" value={form.data.theme_custom_light} onChange={(k,v)=>updateCustom('light',k,v)} /><CustomBuilder skin="dark" value={form.data.theme_custom_dark} onChange={(k,v)=>updateCustom('dark',k,v)} /></>}
        {form.errors.theme_key&&<p className="mt-2 text-sm text-red-600">{form.errors.theme_key}</p>}
      </section>
      <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="mb-4"><h2 className="text-lg font-extrabold text-slate-900">Global Skin</h2><p className="mt-1 text-sm text-slate-500">Only Light and Dark are available. Users can switch skin globally from the floating appearance control; this value is the default for new browsers.</p></div>
        <div className="grid gap-3 sm:grid-cols-2">{(['light','dark'] as Skin[]).map(skin=><button type="button" key={skin} onClick={()=>form.setData('skin_default',skin)} className={'rounded-2xl border-2 p-4 text-left '+(form.data.skin_default===skin?'border-indigo-600 bg-indigo-50':'border-slate-200')}><span className="text-2xl">{skin==='light'?'☀':'☾'}</span><span className="ml-3 font-extrabold text-slate-900">{skin==='light'?'Light':'Dark'}</span><span className="mt-1 block text-xs text-slate-500">Default {skin} skin</span></button>)}</div>
      </section>
      <section className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label className="block"><span className="text-sm font-semibold text-slate-800">Platform name</span><input className="mt-1 w-full rounded-xl border p-3" value={form.data.platform_name} onChange={e=>form.setData('platform_name',e.target.value)} maxLength={80} required /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support email</span><input type="email" className="mt-1 w-full rounded-xl border p-3" value={form.data.support_email} onChange={e=>form.setData('support_email',e.target.value)} maxLength={254} /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support notice</span><textarea className="mt-1 min-h-24 w-full rounded-xl border p-3" value={form.data.support_notice} onChange={e=>form.setData('support_notice',e.target.value)} maxLength={500} /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Default timezone</span><input className="mt-1 w-full rounded-xl border p-3" value={form.data.default_timezone} onChange={e=>form.setData('default_timezone',e.target.value)} placeholder="Africa/Lagos" required /></label>
      </section>
      <div className="flex flex-wrap items-center gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing?'Saving…':'Save & publish appearance'}</button>{form.recentlySuccessful&&<span className="text-sm text-emerald-700">Appearance settings saved and published globally.</span>}</div>
    </form>
  </div></main></>;
}
