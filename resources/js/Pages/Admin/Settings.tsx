import { Head, useForm } from '@inertiajs/react';
import { THEMES, DEFAULT_CUSTOM, type Palette, type Skin } from '../../Utils/ThemeSystem';

type Props = { settings: {
  platform_name:string; support_email:string; support_notice:string; default_timezone:string; theme_key:string; theme_primary:string; skin_default:Skin;
  theme_custom_light:Partial<Palette>; theme_custom_dark:Partial<Palette>;
  business:{phone:string;whatsapp:string;email:string;address:string;website:string};
  social:{facebook:string;instagram:string;x:string;youtube:string;tiktok:string;linkedin:string};
  assets:{logo:string;favicon:string;banner:string;hero:string};
  smtp:{enabled:boolean;provider:string;host:string;port:number;encryption:string;username:string;from_address:string;from_name:string};
  smtp_env:{mailer:string;host:string;port:number;encryption:string;from_address:string;from_name:string};
  smtp_providers:{key:string;name:string;host:string;port:number;encryption:string;limit:string}[];
} };
const fields: {key:keyof Palette; label:string}[] = [
  {key:'primary',label:'Primary'},{key:'secondary',label:'Secondary'},{key:'accent',label:'Accent'},{key:'background',label:'Background'},
  {key:'surface',label:'Surface / Cards'},{key:'text',label:'Text'},{key:'muted',label:'Muted Text'},{key:'border',label:'Border'},
  {key:'success',label:'Success'},{key:'warning',label:'Warning'},{key:'danger',label:'Danger'},
];
const validHex=(v:string)=>/^#[0-9A-Fa-f]{6}$/.test(v);

function CustomBuilder({skin,value,onChange}:{skin:Skin;value:Partial<Palette>;onChange:(key:keyof Palette,value:string)=>void}){
  const fallback=DEFAULT_CUSTOM[skin];
  return <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-5">
    <div className="mb-4"><h3 className="font-extrabold text-slate-900">Custom {skin==='light'?'Light':'Dark'} palette</h3><p className="mt-1 text-sm text-slate-500">Choose the colour tokens used globally by the platform. HEX values are validated before publishing.</p></div>
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
    business:settings.business||{phone:'',whatsapp:'',email:'',address:'',website:''}, social:settings.social||{facebook:'',instagram:'',x:'',youtube:'',tiktok:'',linkedin:''},
    smtp:{enabled:settings.smtp?.enabled||false,provider:settings.smtp?.provider||'env',host:settings.smtp?.host||'',port:settings.smtp?.port||587,encryption:settings.smtp?.encryption||'tls',username:settings.smtp?.username||'',password:'',from_address:settings.smtp?.from_address||'',from_name:settings.smtp?.from_name||settings.platform_name},
    theme_key:settings.theme_key||'modern-corporate',theme_primary:settings.theme_primary||'#2563EB',skin_default:settings.skin_default||('light' as Skin),
    theme_custom_light:settings.theme_custom_light||{},theme_custom_dark:settings.theme_custom_dark||{},
  });
  const choose=(key:string)=>{const theme=THEMES.find(t=>t.key===key);if(theme)form.setData(d=>({...d,theme_key:key,theme_primary:theme.light.primary}));};
  const updateCustom=(skin:Skin,key:keyof Palette,value:string)=>{
    form.setData(d=>({...d,[skin==='light'?'theme_custom_light':'theme_custom_dark']:{...(skin==='light'?d.theme_custom_light:d.theme_custom_dark),[key]:value},theme_primary:key==='primary'?value:d.theme_primary}));
  };
  const submit=(e:React.FormEvent)=>{e.preventDefault();form.put('/admin/settings',{preserveScroll:true});};
  const assetForm=useForm<{asset:string;file:File|null}>({asset:'logo',file:null});
  const uploadAsset=(asset:string,file:File|null)=>{if(!file)return; assetForm.setData({asset,file}); setTimeout(()=>assetForm.post('/admin/settings/asset',{forceFormData:true,preserveScroll:true}),0);};
  const smtpTest=useForm({email:''});
  const sendSmtpTest=(e:React.FormEvent)=>{e.preventDefault();smtpTest.post('/admin/settings/smtp-test',{preserveScroll:true});};
  const provider=(key:string)=>settings.smtp_providers?.find(p=>p.key===key);
  return <><Head title="System settings" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <header><p className="text-sm font-semibold text-indigo-700">{settings.platform_name} · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">System settings</h1><p className="mt-2 max-w-3xl text-sm text-slate-600">Configure the global professional design system. Preset themes are original design directions inspired by leading fintech/SaaS patterns, not interface copies. Skin has exactly two options: Light and Dark.</p></header>
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
        <div><h2 className="text-lg font-extrabold text-slate-900">Business Information</h2><p className="mt-1 text-sm text-slate-500">Optional public business/contact details. These are platform settings and can be changed without code edits.</p></div>
        <div className="grid gap-4 md:grid-cols-2">
          {([['phone','Phone'],['whatsapp','WhatsApp'],['email','Business email'],['website','Website'],['address','Business address']] as const).map(([key,label])=><label key={key} className="block"><span className="text-sm font-semibold text-slate-800">{label}</span><input type={key==='email'?'email':key==='website'?'url':'text'} className="mt-1 w-full rounded-xl border p-3" value={form.data.business[key]} onChange={e=>form.setData('business',{...form.data.business,[key]:e.target.value})} /></label>)}
        </div>
        <div><h3 className="font-bold text-slate-900">Social media</h3><div className="mt-3 grid gap-4 md:grid-cols-2">{(['facebook','instagram','x','youtube','tiktok','linkedin'] as const).map(key=><label key={key} className="block"><span className="text-sm font-semibold capitalize text-slate-800">{key}</span><input type="url" className="mt-1 w-full rounded-xl border p-3" value={form.data.social[key]} onChange={e=>form.setData('social',{...form.data.social,[key]:e.target.value})} placeholder="https://..." /></label>)}</div></div>
      </section>
      <section className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div><h2 className="text-lg font-extrabold text-slate-900">Logo, Favicon & Media</h2><p className="mt-1 text-sm text-slate-500">Upload global brand assets. The saved assets are available platform-wide.</p></div>
        <div className="grid gap-4 md:grid-cols-2">{(['logo','favicon','banner','hero'] as const).map(asset=><div key={asset} className="rounded-2xl border p-4"><div className="font-bold capitalize">{asset}</div>{settings.assets?.[asset]&&<img src={settings.assets[asset]} alt={asset} className={asset==='banner'||asset==='hero'?'mt-3 h-24 w-full rounded-xl object-cover':'mt-3 h-16 max-w-[180px] object-contain'} /> }<input type="file" accept={asset==='favicon'?'.ico,.png,.svg':'image/png,image/jpeg,image/webp,image/svg+xml'} className="mt-3 w-full text-sm" onChange={e=>uploadAsset(asset,e.target.files?.[0]||null)} /></div>)}</div>
      </section>
      <section className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div><h2 className="text-lg font-extrabold text-slate-900">SMTP / Email Delivery</h2><p className="mt-1 text-sm text-slate-500">Use an external SMTP relay instead of depending only on cPanel mail. Credentials are stored as a protected platform setting; .env remains the fallback.</p></div>
        <div className="rounded-xl bg-slate-50 p-4 text-sm"><b>Current .env/cPanel mail:</b> {settings.smtp_env.mailer} · {settings.smtp_env.host || 'not configured'}:{settings.smtp_env.port}</div>
        <div className="grid gap-4 md:grid-cols-2"><label className="flex items-center gap-3 rounded-xl border p-3"><input type="checkbox" checked={!!form.data.smtp.enabled} onChange={e=>form.setData('smtp',{...form.data.smtp,enabled:e.target.checked})}/><span><b>Enable platform SMTP</b><span className="block text-xs text-slate-500">When enabled, this takes priority over .env mail settings.</span></span></label>
        <label className="block"><span className="text-sm font-semibold">Provider</span><select className="mt-1 w-full rounded-xl border p-3" value={form.data.smtp.provider} onChange={e=>{const p=provider(e.target.value);form.setData('smtp',{...form.data.smtp,provider:e.target.value,host:p?.host||form.data.smtp.host,port:p?.port||587,encryption:p?.encryption||'tls'});}}><option value="env">Use .env / cPanel</option>{settings.smtp_providers.map(p=><option key={p.key} value={p.key}>{p.name} — {p.limit}</option>)}</select></label></div>
        <div className="grid gap-4 md:grid-cols-2"><input className="rounded-xl border p-3" placeholder="SMTP host" value={form.data.smtp.host} onChange={e=>form.setData('smtp',{...form.data.smtp,host:e.target.value})}/><input type="number" className="rounded-xl border p-3" placeholder="Port" value={form.data.smtp.port} onChange={e=>form.setData('smtp',{...form.data.smtp,port:Number(e.target.value)})}/><select className="rounded-xl border p-3" value={form.data.smtp.encryption} onChange={e=>form.setData('smtp',{...form.data.smtp,encryption:e.target.value})}><option value="tls">TLS</option><option value="ssl">SSL</option><option value="null">None</option></select><input className="rounded-xl border p-3" placeholder="SMTP username" value={form.data.smtp.username} onChange={e=>form.setData('smtp',{...form.data.smtp,username:e.target.value})}/><input type="password" className="rounded-xl border p-3" placeholder="SMTP password (leave blank to keep existing)" value={form.data.smtp.password} onChange={e=>form.setData('smtp',{...form.data.smtp,password:e.target.value})}/><input type="email" className="rounded-xl border p-3" placeholder="From email" value={form.data.smtp.from_address} onChange={e=>form.setData('smtp',{...form.data.smtp,from_address:e.target.value})}/><input className="rounded-xl border p-3" placeholder="From name" value={form.data.smtp.from_name} onChange={e=>form.setData('smtp',{...form.data.smtp,from_name:e.target.value})}/></div>
        <form onSubmit={sendSmtpTest} className="flex flex-wrap gap-3"><input type="email" required className="flex-1 rounded-xl border p-3" placeholder="Send test email to..." value={smtpTest.data.email} onChange={e=>smtpTest.setData('email',e.target.value)}/><button className="rounded-xl border px-4 py-3 font-semibold" disabled={smtpTest.processing}>Send SMTP test</button></form>
      </section>
      <section className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <label className="block"><span className="text-sm font-semibold text-slate-800">Site identity</span><p className="mt-1 text-xs text-slate-500">Change this once and the new identity is published across the public site, user area, admin area, browser title and PWA metadata.</p><input className="mt-2 w-full rounded-xl border p-3" value={form.data.platform_name} onChange={e=>form.setData('platform_name',e.target.value)} maxLength={80} required /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support email</span><input type="email" className="mt-1 w-full rounded-xl border p-3" value={form.data.support_email} onChange={e=>form.setData('support_email',e.target.value)} maxLength={254} /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Support notice</span><textarea className="mt-1 min-h-24 w-full rounded-xl border p-3" value={form.data.support_notice} onChange={e=>form.setData('support_notice',e.target.value)} maxLength={500} /></label>
        <label className="block"><span className="text-sm font-semibold text-slate-800">Default timezone</span><input className="mt-1 w-full rounded-xl border p-3" value={form.data.default_timezone} onChange={e=>form.setData('default_timezone',e.target.value)} placeholder="Africa/Lagos" required /></label>
      </section>
      <div className="flex flex-wrap items-center gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing?'Saving…':'Save & publish appearance'}</button>{form.recentlySuccessful&&<span className="text-sm text-emerald-700">Appearance settings saved and published globally.</span>}</div>
    </form>
  </div></main></>;
}
