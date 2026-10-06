import { Head, useForm } from '@inertiajs/react';
import { THEMES, DEFAULT_CUSTOM, type Palette, type Skin } from '../../Utils/ThemeSystem';

type ProviderPreset = {
  key:string; name:string; host:string; port:number; encryption:string; limit:string; setup:string;
};
type SmtpProfile = {
  key:string; name:string; provider:string; enabled:boolean; priority:number; weight:number;
  host:string; port:number; encryption:string; username:string; password:string; from_address:string; from_name:string;
};
type Props = { settings:any; smtp_env:{mailer:string;host:string;port:number;encryption:string;from_address:string;from_name:string}; smtp_providers:ProviderPreset[] };

const fields:{key:keyof Palette;label:string}[]=[
  {key:'primary',label:'Primary'},{key:'secondary',label:'Secondary'},{key:'accent',label:'Accent'},{key:'background',label:'Background'},
  {key:'surface',label:'Surface / Cards'},{key:'text',label:'Text'},{key:'muted',label:'Muted Text'},{key:'border',label:'Border'},
  {key:'success',label:'Success'},{key:'warning',label:'Warning'},{key:'danger',label:'Danger'},
];
const validHex=(v:string)=>/^#[0-9A-Fa-f]{6}$/.test(v);

function CustomBuilder({skin,value,onChange}:{skin:Skin;value:Partial<Palette>;onChange:(key:keyof Palette,value:string)=>void}){
  const fallback=DEFAULT_CUSTOM[skin];
  return <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-5">
    <h3 className="font-extrabold text-slate-900">Custom {skin==='light'?'Light':'Dark'} palette</h3>
    <p className="mt-1 text-sm text-slate-500">HEX colours are validated before publishing.</p>
    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {fields.map(field=>{const current=(value[field.key] as string)||fallback[field.key];return <label key={String(field.key)} className="rounded-xl border border-slate-200 bg-white p-3">
        <span className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">{field.label}</span>
        <div className="flex items-center gap-2"><input type="color" value={validHex(current)?current:fallback[field.key]} onChange={e=>onChange(field.key,e.target.value)} className="h-10 w-12 cursor-pointer"/><input value={current} onChange={e=>onChange(field.key,e.target.value)} maxLength={7} className="min-w-0 flex-1 rounded-lg border px-2.5 py-2 font-mono text-sm"/></div>
      </label>})}
    </div>
  </div>;
}

function setupText(p:ProviderPreset){
  return <div className="mt-3 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><b>Setup:</b> {p.setup}</div>;
}

export default function SettingsPage({settings,smtp_env,smtp_providers}:Props){
  const initialProfiles:SmtpProfile[]=(settings.smtp?.profiles||[]).map((p:SmtpProfile)=>({...p,password:''}));
  const form=useForm<any>({
    platform_name:settings.platform_name,support_email:settings.support_email,support_notice:settings.support_notice,default_timezone:settings.default_timezone,
    business:settings.business||{phone:'',whatsapp:'',email:'',address:'',website:''},
    social:settings.social||{facebook:'',instagram:'',x:'',youtube:'',tiktok:'',linkedin:''},
    smtp:{enabled:!!settings.smtp?.enabled,strategy:settings.smtp?.strategy||'failover',profiles:initialProfiles},
    theme_key:settings.theme_key||'modern-corporate',theme_primary:settings.theme_primary||'#2563EB',skin_default:settings.skin_default||('light' as Skin),
    theme_custom_light:settings.theme_custom_light||{},theme_custom_dark:settings.theme_custom_dark||{},
  });

  const choose=(key:string)=>{const theme=THEMES.find(t=>t.key===key);if(theme)form.setData((d:any)=>({...d,theme_key:key,theme_primary:theme.light.primary}));};
  const updateCustom=(skin:Skin,key:keyof Palette,value:string)=>form.setData((d:any)=>({...d,[skin==='light'?'theme_custom_light':'theme_custom_dark']:{...(skin==='light'?d.theme_custom_light:d.theme_custom_dark),[key]:value},theme_primary:key==='primary'?value:d.theme_primary}));
  const submit=(e:React.FormEvent)=>{e.preventDefault();form.put('/admin/settings',{preserveScroll:true});};

  const assetForm=useForm<{asset:string;file:File|null}>({asset:'logo',file:null});
  const uploadAsset=(asset:string,file:File|null)=>{if(!file)return;assetForm.setData({asset,file});setTimeout(()=>assetForm.post('/admin/settings/asset',{forceFormData:true,preserveScroll:true}),0);};

  const smtpTest=useForm({email:'',profile_key:''});
  const smtpHealth=useForm({email:''});
  const restoreForm=useForm<{backup:File|null}>({backup:null});
  const clearCache=useForm({});
  const restoreBackup=(e:React.FormEvent)=>{e.preventDefault();if(!restoreForm.data.backup)return; if(!window.confirm('Restore this backup? Current database records will be replaced.'))return; restoreForm.post('/admin/maintenance/restore',{forceFormData:true,preserveScroll:true});};
  const clearWebsiteCache=(e:React.MouseEvent)=>{e.preventDefault();if(window.confirm('Clear application cache now?'))clearCache.post('/admin/maintenance/cache-clear',{preserveScroll:true});};
  const runHealth=(e:React.FormEvent)=>{e.preventDefault();smtpHealth.post('/admin/settings/smtp-health',{preserveScroll:true});};
  const sendSmtpTest=(e:React.FormEvent)=>{e.preventDefault();smtpTest.post('/admin/settings/smtp-test',{preserveScroll:true});};

  const profiles:SmtpProfile[]=form.data.smtp.profiles||[];
  const addProfile=(preset?:ProviderPreset)=>{
    const key=(preset?.key||'custom')+'_'+Date.now().toString().slice(-6);
    const p:SmtpProfile={key,name:preset?.name||'Custom SMTP',provider:preset?.key||'custom',enabled:true,priority:profiles.length+1,weight:1,host:preset?.host||'',port:preset?.port||587,encryption:preset?.encryption||'tls',username:'',password:'',from_address:'',from_name:settings.platform_name};
    form.setData('smtp',{...form.data.smtp,profiles:[...profiles,p]});
  };
  const patchProfile=(index:number,patch:Partial<SmtpProfile>)=>form.setData('smtp',{...form.data.smtp,profiles:profiles.map((p,i)=>i===index?{...p,...patch}:p)});
  const removeProfile=(index:number)=>form.setData('smtp',{...form.data.smtp,profiles:profiles.filter((_,i)=>i!==index)});
  const moveProfile=(index:number,dir:-1|1)=>{
    const next=index+dir;if(next<0||next>=profiles.length)return;
    const copy=[...profiles];[copy[index],copy[next]]=[copy[next],copy[index]];
    form.setData('smtp',{...form.data.smtp,profiles:copy.map((p,i)=>({...p,priority:i+1}))});
  };

  return <><Head title="System settings"/><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <header><p className="text-sm font-semibold text-indigo-700">{settings.platform_name} · ADMIN</p><h1 className="mt-1 text-2xl font-extrabold text-slate-900">System settings</h1><p className="mt-2 max-w-3xl text-sm text-slate-600">Global identity, business information, media, theme and production email delivery.</p></header>

    <form onSubmit={submit} className="mt-6 space-y-6">
      <section className="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 className="text-lg font-extrabold">Global Theme · 11 options</h2><p className="mt-1 text-sm text-slate-500">Applied platform-wide.</p>
        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {THEMES.map(theme=><button type="button" key={theme.key} onClick={()=>choose(theme.key)} className={'rounded-2xl border-2 p-4 text-left '+(form.data.theme_key===theme.key?'border-indigo-600 bg-indigo-50':'border-slate-200')}><div className="flex gap-3"><span className="h-12 w-12 shrink-0 rounded-xl" style={{background:'linear-gradient(135deg,'+theme.light.primary+','+theme.light.accent+')'}}/><span><b className="block">{theme.name}</b><span className="text-xs text-slate-500">{theme.description}</span></span></div></button>)}
          <button type="button" onClick={()=>choose('custom')} className={'rounded-2xl border-2 p-4 text-left '+(form.data.theme_key==='custom'?'border-indigo-600 bg-indigo-50':'border-slate-200')}><b>🎨 Custom Theme</b><span className="mt-1 block text-xs text-slate-500">Admin-defined Light and Dark palettes.</span></button>
        </div>
        {form.data.theme_key==='custom'&&<><CustomBuilder skin="light" value={form.data.theme_custom_light} onChange={(k,v)=>updateCustom('light',k,v)}/><CustomBuilder skin="dark" value={form.data.theme_custom_dark} onChange={(k,v)=>updateCustom('dark',k,v)}/></>}
      </section>

      <section className="rounded-2xl border bg-white p-5 shadow-sm"><h2 className="text-lg font-extrabold">Global Skin</h2><div className="mt-4 grid gap-3 sm:grid-cols-2">{(['light','dark'] as Skin[]).map(s=><button type="button" key={s} onClick={()=>form.setData('skin_default',s)} className={'rounded-2xl border-2 p-4 text-left '+(form.data.skin_default===s?'border-indigo-600 bg-indigo-50':'border-slate-200')}>{s==='light'?'☀':'☾'} <b className="ml-2">{s==='light'?'Light':'Dark'}</b></button>)}</div></section>

      <section className="space-y-5 rounded-2xl border bg-white p-5 shadow-sm">
        <div><h2 className="text-lg font-extrabold">Business Information & Social Media</h2><p className="mt-1 text-sm text-slate-500">Optional details that can be displayed globally when configured.</p></div>
        <div className="grid gap-4 md:grid-cols-2">{([['phone','Phone'],['whatsapp','WhatsApp'],['email','Business email'],['website','Website'],['address','Business address']] as const).map(([key,label])=><label key={key} className="block"><span className="text-sm font-semibold">{label}</span><input type={key==='email'?'email':key==='website'?'url':'text'} className="mt-1 w-full rounded-xl border p-3" value={form.data.business[key]} onChange={e=>form.setData('business',{...form.data.business,[key]:e.target.value})}/></label>)}</div>
        <div><h3 className="font-bold">Social media</h3><div className="mt-3 grid gap-4 md:grid-cols-2">{(['facebook','instagram','x','youtube','tiktok','linkedin'] as const).map(k=><label key={k}><span className="text-sm font-semibold capitalize">{k}</span><input type="url" className="mt-1 w-full rounded-xl border p-3" placeholder="https://..." value={form.data.social[k]} onChange={e=>form.setData('social',{...form.data.social,[k]:e.target.value})}/></label>)}</div></div>
      </section>

      <section className="rounded-2xl border bg-white p-5 shadow-sm"><h2 className="text-lg font-extrabold">Logo, Favicon & Media</h2><p className="mt-1 text-sm text-slate-500">Upload once and use the assets throughout the platform.</p>
        <div className="mt-5 grid gap-4 md:grid-cols-2">{(['logo','favicon','banner','hero'] as const).map(asset=><div key={asset} className="rounded-2xl border p-4"><b className="capitalize">{asset}</b>{settings.assets?.[asset]&&<img src={settings.assets[asset]} alt={asset} className={asset==='banner'||asset==='hero'?'mt-3 h-24 w-full rounded-xl object-cover':'mt-3 h-16 max-w-[180px] object-contain'}/>}<input type="file" accept={asset==='favicon'?'.ico,.png,.jpg,.jpeg,.webp':'image/png,image/jpeg,image/webp'} className="mt-3 w-full text-sm" onChange={e=>uploadAsset(asset,e.target.files?.[0]||null)}/></div>)}</div>
      </section>

      <section className="space-y-6 rounded-2xl border bg-white p-5 shadow-sm">
        <div><h2 className="text-lg font-extrabold">SMTP Delivery Pool</h2><p className="mt-1 text-sm text-slate-500">Add multiple SMTP accounts. Enable 2 or all 8 providers. The platform can automatically fail over or distribute emails across the enabled pool.</p></div>
        <div className="grid gap-4 md:grid-cols-3">
          <label className="flex items-center gap-3 rounded-xl border p-4 md:col-span-1"><input type="checkbox" checked={!!form.data.smtp.enabled} onChange={e=>form.setData('smtp',{...form.data.smtp,enabled:e.target.checked})}/><span><b>Enable SMTP pool</b><span className="block text-xs text-slate-500">Disabled = use .env/cPanel mail.</span></span></label>
          <label className="rounded-xl border p-4"><b className="text-sm">Delivery strategy</b><select className="mt-2 w-full rounded-xl border p-3" value={form.data.smtp.strategy} onChange={e=>form.setData('smtp',{...form.data.smtp,strategy:e.target.value})}><option value="failover">Failover — priority first, next provider only if sending fails</option><option value="roundrobin">Round-robin — actively distribute mail across all enabled providers</option></select></label>
          <div className="rounded-xl border bg-slate-50 p-4 text-sm"><b>.env / cPanel fallback</b><div className="mt-1">{smtp_env.mailer} · {smtp_env.host||'not configured'}:{smtp_env.port}</div></div>
        </div>

        <div className="rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900"><b>How it works:</b> Failover tries enabled profiles by priority and moves to the next profile after a transport failure. Round-robin uses every enabled profile over successive sends and can retry another profile when one fails. This uses Laravel/Symfony's native failover and round-robin mail transports.</div>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{smtp_providers.map(p=><button type="button" key={p.key} onClick={()=>addProfile(p)} className="rounded-xl border p-4 text-left hover:border-indigo-400"><b>{p.name}</b><span className="mt-1 block text-xs text-slate-500">{p.host}:{p.port} · {p.encryption.toUpperCase()}</span><span className="mt-1 block text-xs font-semibold text-indigo-700">+ Add provider</span></button>)}</div>

        <div className="space-y-4">{profiles.length===0&&<div className="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500">No SMTP profiles yet. Add SendPulse, Gmail, cPanel or another provider above.</div>}
          {profiles.map((p,i)=>{const preset=smtp_providers.find(x=>x.key===p.provider);return <div key={p.key} className="rounded-2xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3"><div><div className="flex items-center gap-2"><b className="text-lg">{p.name}</b>{p.enabled?<span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">ENABLED</span>:<span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">DISABLED</span>}</div><span className="text-xs text-slate-500">{p.provider} · priority {p.priority}</span></div><div className="flex gap-2"><button type="button" onClick={()=>moveProfile(i,-1)} className="rounded-lg border px-3 py-2 text-xs">↑</button><button type="button" onClick={()=>moveProfile(i,1)} className="rounded-lg border px-3 py-2 text-xs">↓</button><button type="button" onClick={()=>removeProfile(i)} className="rounded-lg border border-red-200 px-3 py-2 text-xs text-red-600">Remove</button></div></div>
            <div className="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
              <input className="rounded-xl border p-3" placeholder="Profile name" value={p.name} onChange={e=>patchProfile(i,{name:e.target.value})}/>
              <input className="rounded-xl border p-3" placeholder="SMTP host" value={p.host} onChange={e=>patchProfile(i,{host:e.target.value})}/>
              <input type="number" className="rounded-xl border p-3" placeholder="Port" value={p.port} onChange={e=>patchProfile(i,{port:Number(e.target.value)})}/>
              <select className="rounded-xl border p-3" value={p.encryption} onChange={e=>patchProfile(i,{encryption:e.target.value})}><option value="tls">TLS</option><option value="ssl">SSL</option><option value="null">None</option></select>
              <input className="rounded-xl border p-3" placeholder="SMTP username" value={p.username} onChange={e=>patchProfile(i,{username:e.target.value})}/>
              <input type="password" className="rounded-xl border p-3" placeholder="Password / App Password" value={p.password} onChange={e=>patchProfile(i,{password:e.target.value})}/>
              <input type="email" className="rounded-xl border p-3" placeholder="From email" value={p.from_address} onChange={e=>patchProfile(i,{from_address:e.target.value})}/>
              <input className="rounded-xl border p-3" placeholder="From name" value={p.from_name} onChange={e=>patchProfile(i,{from_name:e.target.value})}/>
              <label className="flex items-center gap-2 rounded-xl border p-3"><input type="checkbox" checked={!!p.enabled} onChange={e=>patchProfile(i,{enabled:e.target.checked})}/> Enabled</label>
              <label className="rounded-xl border p-3"><span className="text-xs font-bold">Priority</span><input type="number" min={1} className="ml-2 w-20 rounded-lg border p-1" value={p.priority} onChange={e=>patchProfile(i,{priority:Number(e.target.value)})}/></label>
              <label className="rounded-xl border p-3"><span className="text-xs font-bold">Weight</span><input type="number" min={1} max={100} className="ml-2 w-20 rounded-lg border p-1" value={p.weight} onChange={e=>patchProfile(i,{weight:Number(e.target.value)})}/></label>
              <button type="button" className="rounded-xl border px-4 py-3 font-semibold" onClick={()=>{smtpTest.setData({email:smtpTest.data.email,profile_key:p.key});document.getElementById('smtp-test')?.scrollIntoView({behavior:'smooth'});}}>Select for test</button>
            </div>
            {preset&&setupText(preset)}
          </div>})}
        </div>

        <div id="smtp-test" className="rounded-2xl border bg-slate-50 p-4"><b>Test an SMTP profile</b><form onSubmit={sendSmtpTest} className="mt-3 flex flex-wrap gap-3"><select required className="rounded-xl border p-3" value={smtpTest.data.profile_key} onChange={e=>smtpTest.setData('profile_key',e.target.value)}><option value="">Choose profile</option>{profiles.map(p=><option key={p.key} value={p.key}>{p.name}</option>)}</select><input type="email" required className="min-w-[220px] flex-1 rounded-xl border p-3" placeholder="Send test email to..." value={smtpTest.data.email} onChange={e=>smtpTest.setData('email',e.target.value)}/><button className="rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white" disabled={smtpTest.processing}>Send test</button></form></div>

        <div className="rounded-2xl border border-indigo-100 bg-indigo-50 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-extrabold text-indigo-950">SMTP Health Monitor</h3><p className="mt-1 text-sm text-indigo-800">Test every configured provider in one action. Results are recorded privately for diagnostics.</p></div><form onSubmit={runHealth} className="flex gap-2"><input type="email" required className="rounded-xl border p-3" placeholder="Test recipient email" value={smtpHealth.data.email} onChange={e=>smtpHealth.setData("email",e.target.value)}/><button disabled={smtpHealth.processing} className="rounded-xl bg-indigo-700 px-4 py-3 font-semibold text-white">{smtpHealth.processing?"Testing…":"Test All SMTP"}</button></form></div><div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{profiles.map(p=>{const h=settings.smtp?.health?.[p.key];return <div key={p.key} className="rounded-xl bg-white p-3"><b>{p.name}</b><div className="mt-1 text-xs text-slate-500">{h?.status==="healthy"?"🟢 Healthy":h?.status==="failed"?"🔴 Failed":"⚪ Not tested"} {h?.latency_ms?" · "+h.latency_ms+"ms":""}</div>{h?.failure_count>0&&<div className="mt-1 text-xs text-red-600">Failures: {h.failure_count}</div>}</div>})}</div></div><div className="rounded-2xl border p-4"><h3 className="font-extrabold">Provider setup guide</h3><div className="mt-4 grid gap-4 md:grid-cols-2">{smtp_providers.map(p=><div key={p.key} className="rounded-xl border p-4"><b>{p.name}</b><div className="mt-2 text-xs text-slate-600"><b>Server:</b> {p.host} · <b>Port:</b> {p.port} · <b>Security:</b> {p.encryption.toUpperCase()}</div><p className="mt-2 text-sm text-slate-600">{p.setup}</p>{p.key==='gmail'&&<p className="mt-2 text-xs font-semibold text-amber-700">Do not use the normal Gmail password. Use a Google App Password after 2-Step Verification.</p>}{p.key==='cpanel'&&<p className="mt-2 text-xs font-semibold text-slate-700">cPanel normally recommends authenticated Secure SSL/TLS SMTP; the exact hostname is shown under Connect Devices.</p>}</div>)}</div></div>
      </section>

      <section className="space-y-4 rounded-2xl border bg-white p-5 shadow-sm"><label className="block"><b>Site identity</b><p className="mt-1 text-xs text-slate-500">Change once and publish globally across public, user, admin and PWA metadata.</p><input className="mt-2 w-full rounded-xl border p-3" value={form.data.platform_name} onChange={e=>form.setData('platform_name',e.target.value)} maxLength={80} required/></label><label className="block"><b>Support email</b><input type="email" className="mt-1 w-full rounded-xl border p-3" value={form.data.support_email} onChange={e=>form.setData('support_email',e.target.value)}/></label><label className="block"><b>Support notice</b><textarea className="mt-1 min-h-24 w-full rounded-xl border p-3" value={form.data.support_notice} onChange={e=>form.setData('support_notice',e.target.value)}/></label><label className="block"><b>Default timezone</b><input className="mt-1 w-full rounded-xl border p-3" value={form.data.default_timezone} onChange={e=>form.setData('default_timezone',e.target.value)} placeholder="Africa/Lagos"/></label></section>

      <section className="space-y-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <div><h2 className="text-lg font-extrabold text-amber-950">System Maintenance</h2><p className="mt-1 text-sm text-amber-900">Production-safe maintenance tools. Backups contain the database and public uploaded files; secrets such as .env are never included.</p></div>
        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-2xl bg-white p-4 shadow-sm"><b className="block">Website Backup</b><p className="mt-1 text-xs text-slate-500">Create and download a portable SEMIZZY ONE backup.</p><a href="/admin/maintenance/backup" className="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Create & Download Backup</a></div>
          <form onSubmit={restoreBackup} className="rounded-2xl bg-white p-4 shadow-sm"><b className="block">Restore Backup</b><p className="mt-1 text-xs text-slate-500">Restores database records and public uploads from a verified SEMIZZY ONE backup.</p><input type="file" accept=".zip,application/zip" className="mt-3 w-full text-sm" onChange={e=>restoreForm.setData('backup',e.target.files?.[0]||null)} required/><button disabled={restoreForm.processing} className="mt-4 rounded-xl bg-amber-700 px-4 py-3 text-sm font-semibold text-white">{restoreForm.processing?'Restoring…':'Restore Backup'}</button></form>
          <div className="rounded-2xl bg-white p-4 shadow-sm"><b className="block">Clear Website Cache</b><p className="mt-1 text-xs text-slate-500">Clears Laravel application, route, config, view and event caches.</p><button onClick={clearWebsiteCache} disabled={clearCache.processing} className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{clearCache.processing?'Clearing…':'Clear Website Cache'}</button></div>
        </div>
      </section>

      <div className="flex flex-wrap items-center gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white">{form.processing?'Saving…':'Save & publish settings'}</button>{form.recentlySuccessful&&<span className="text-sm text-emerald-700">Settings saved and published globally.</span>}</div>
    </form>
  </div></main></>;
}
