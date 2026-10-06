import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Provider = {
  id: number;
  identifier: string;
  display_name: string;
  base_url: string | null;
  documentation_url: string | null;
  official_website: string | null;
  environment: string;
  auth_type: string;
  verification_status: string;
  integration_status: string;
  enabled: boolean;
  paused: boolean;
  priority: number;
  last_tested_at: string | null;
  last_test_status: string | null;
  last_test_summary: string | null;
  credentials: Record<string, string | null>;
  capabilities: string[];
  endpoints: Record<string, unknown>;
  service_categories: string[];
};

type FormData = {
  identifier: string;
  display_name: string;
  base_url: string;
  documentation_url: string;
  official_website: string;
  environment: 'sandbox' | 'production';
  auth_type: 'none' | 'api_key' | 'bearer_token' | 'basic_auth' | 'oauth2' | 'custom';
  priority: number;
  integration_config: string;
  credentials_json: string;
  credentials_touched: boolean;
};

const emptyForm: FormData = {
  identifier: '',
  display_name: '',
  base_url: '',
  documentation_url: '',
  official_website: '',
  environment: 'sandbox',
  auth_type: 'bearer_token',
  priority: 100,
  integration_config: JSON.stringify({ capabilities: ['health'], endpoints: {}, service_categories: [] }, null, 2),
  credentials_json: JSON.stringify({ headers_get: {}, headers_post: {} }, null, 2),
  credentials_touched: false,
};

export default function Providers({ providers }: { providers: Provider[] }) {
  const [editingId, setEditingId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkBusy, setBulkBusy] = useState(false);
  const form = useForm<FormData>(emptyForm);

  const beginEdit = (provider: Provider) => {
    setEditingId(provider.id);
    form.setData({
      identifier: provider.identifier,
      display_name: provider.display_name,
      base_url: provider.base_url ?? '',
      documentation_url: provider.documentation_url ?? '',
      official_website: provider.official_website ?? '',
      environment: provider.environment as FormData['environment'],
      auth_type: provider.auth_type as FormData['auth_type'],
      priority: provider.priority,
      integration_config: JSON.stringify({
        capabilities: provider.capabilities,
        endpoints: provider.endpoints,
        service_categories: provider.service_categories,
      }, null, 2),
      credentials_json: JSON.stringify({ headers_get: {}, headers_post: {} }, null, 2),
      credentials_touched: false,
    });
    form.clearErrors();
  };

  const cancelEdit = () => {
    setEditingId(null);
    form.setData(emptyForm);
    form.clearErrors();
  };

  const submit = (event: React.FormEvent) => {
    event.preventDefault();
    let config: Record<string, unknown>;
    let credentials: Record<string, unknown> | undefined;
    if (form.data.credentials_touched) {
      try {
        const parsedCredentials: unknown = JSON.parse(form.data.credentials_json || '{}');
        if (!parsedCredentials || typeof parsedCredentials !== 'object' || Array.isArray(parsedCredentials)) throw new Error('Credentials must be a JSON object.');
        credentials = parsedCredentials as Record<string, unknown>;
      } catch (error) {
        form.setError('credentials_json', error instanceof Error ? error.message : 'Invalid credentials JSON.');
        return;
      }
    }
    try {
      const parsed: unknown = JSON.parse(form.data.integration_config || '{}');
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) throw new Error('Configuration must be a JSON object.');
      config = parsed as Record<string, unknown>;
      for (const key of ['capabilities', 'endpoints', 'service_categories', 'credentials']) {
        if (config[key] !== undefined && (config[key] === null || typeof config[key] !== 'object')) {
          throw new Error(`${key} must be a JSON object or array.`);
        }
      }
    } catch (error) {
      form.setError('integration_config', error instanceof Error ? error.message : 'Invalid JSON configuration.');
      return;
    }

    const payload = {
      identifier: form.data.identifier,
      display_name: form.data.display_name,
      base_url: form.data.base_url || null,
      documentation_url: form.data.documentation_url || null,
      official_website: form.data.official_website || null,
      environment: form.data.environment,
      auth_type: form.data.auth_type,
      priority: Number(form.data.priority),
      ...config,
      ...(credentials !== undefined ? { credentials } : {}),
    };

    if (editingId) {
      router.patch(`/admin/providers/${editingId}`, payload as any, { preserveScroll: true, onSuccess: cancelEdit });
    } else {
      router.post('/admin/providers', payload as any, { preserveScroll: true, onSuccess: () => { form.reset(); form.setData(emptyForm); } });
    }
  };

  const selectableIds = providers.map(p => p.id);
  const allSelected = selectableIds.length > 0 && selectableIds.every(id => selectedIds.includes(id));

  const toggleSelected = (id: number) => setSelectedIds(ids => ids.includes(id) ? ids.filter(x => x !== id) : [...ids, id]);
  const toggleAll = () => setSelectedIds(allSelected ? [] : selectableIds);

  const csrfToken = () => { const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content; if (meta) return meta; const cookie = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN=')); return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : ''; };
  const runJson = async (url: string, data: Record<string, unknown> = {}, method = 'POST') => {
    const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() }, credentials: 'same-origin', body: method === 'GET' ? undefined : JSON.stringify(data) });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || `Request failed (${response.status})`);
    return payload;
  };

  const runBulk = async (url: string, method: 'post' | 'delete', confirmText: string, data: Record<string, unknown> = {}) => {
    if (!selectedIds.length || !window.confirm(confirmText)) return;
    setBulkBusy(true);
    try { setBulkBusy(true); const result = await runJson(url, { provider_ids: selectedIds, ...data }, method === 'delete' ? 'DELETE' : 'POST'); setSelectedIds([]); const summary = result?.message || 'Bulk action completed.'; window.alert(summary); window.location.reload(); } finally { setBulkBusy(false); }
    catch (error) { window.alert(error instanceof Error ? error.message : 'Provider bulk action failed.'); }
    finally { setBulkBusy(false); }
  };

  const remove = async (provider: Provider) => {
    if (!window.confirm(`Remove ${provider.display_name} from the active provider registry? Historical records will be retained.`)) return;
    try { await runJson(`/admin/providers/${provider.id}`, {}, 'DELETE'); window.location.reload(); }
    catch (error) { window.alert(error instanceof Error ? error.message : 'Provider removal failed.'); }
  };

  return <><Head title="API Providers" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
    <header className="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><h1 className="text-3xl font-extrabold text-slate-900">API Providers</h1><p className="mt-2 text-slate-600">Add as many providers as needed. New providers are always saved disabled and unverified; test and verify real credentials before enabling.</p></div><div className="flex flex-wrap gap-2">
<button type="button" onClick={()=>{if(window.confirm('Install the built-in provider catalogue and service mappings? Existing provider credentials will not be overwritten.')) router.post('/admin/providers/install-presets', {}, {preserveScroll:true})}} className="rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Install provider catalogue</button>
<button type="button" onClick={()=>{if(window.confirm('Sync all enabled and verified providers now? Failed providers/services will be skipped and reported; no credentials or selling prices will be changed.')) router.post('/admin/catalogue/sync-all', {}, {preserveScroll:true})}} className="rounded-xl border border-indigo-200 bg-white px-4 py-3 text-sm font-bold text-indigo-700">Sync all verified catalogues</button>{providers.length > 0 && <button type="button" onClick={toggleAll} className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold">{allSelected ? 'Clear selection' : 'Select all'}</button>}
</div></header>

    <form onSubmit={submit} className="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-bold">{editingId ? 'Edit provider configuration' : 'Add provider'}</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        {!editingId && <label className="block text-sm font-semibold">Unique identifier<input required maxLength={100} pattern="[A-Za-z0-9_-]+" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="provider-name" value={form.data.identifier} onChange={e=>form.setData('identifier',e.target.value)} />{form.errors.identifier && <span className="mt-1 block text-red-600">{form.errors.identifier}</span>}</label>}
        <label className="block text-sm font-semibold">Display name<input required maxLength={160} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="Provider display name" value={form.data.display_name} onChange={e=>form.setData('display_name',e.target.value)} />{form.errors.display_name && <span className="mt-1 block text-red-600">{form.errors.display_name}</span>}</label>
        <label className="block text-sm font-semibold">Base API URL<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="https://api.example.com" value={form.data.base_url} onChange={e=>form.setData('base_url',e.target.value)} />{form.errors.base_url && <span className="mt-1 block text-red-600">{form.errors.base_url}</span>}</label>
        <label className="block text-sm font-semibold">API documentation URL<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.documentation_url} onChange={e=>form.setData('documentation_url',e.target.value)} /></label>
        <label className="block text-sm font-semibold">Official website<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.official_website} onChange={e=>form.setData('official_website',e.target.value)} /></label>
        <label className="block text-sm font-semibold">Environment<select className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.environment} onChange={e=>form.setData('environment',e.target.value as FormData['environment'])}><option value="sandbox">Sandbox</option><option value="production">Production</option></select></label>
        <label className="block text-sm font-semibold">Authentication type<select className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.auth_type} onChange={e=>form.setData('auth_type',e.target.value as FormData['auth_type'])}><option value="none">No authentication</option><option value="api_key">API key</option><option value="bearer_token">API token / Bearer token</option><option value="basic_auth">Username + password</option><option value="oauth2">OAuth 2 access token</option><option value="custom">Custom authorization</option></select></label>
        <label className="block text-sm font-semibold">Priority (lower is earlier)<input type="number" min={0} max={100000} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.priority} onChange={e=>form.setData('priority',Number(e.target.value))} /></label>
      </div>
      <label className="block text-sm font-semibold">Integration configuration (JSON)<textarea rows={8} spellCheck={false} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-mono text-xs font-normal" value={form.data.integration_config} onChange={e=>form.setData('integration_config',e.target.value)} />{form.errors.integration_config && <span className="mt-1 block text-red-600">{form.errors.integration_config}</span>}<span className="mt-1 block text-xs font-normal text-slate-500">Capabilities, operation endpoints and service categories. Credentials are configured separately and stored encrypted.</span></label>
      <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div className="flex items-start justify-between gap-3"><div><h3 className="text-base font-bold text-slate-900">Provider credentials</h3><p className="mt-1 text-xs text-slate-600">Add only the credentials required by the provider. Values are encrypted at rest and masked after saving.</p></div><span className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">Secret</span></div>
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
<label key="api_key" className="text-xs font-bold uppercase tracking-wide text-slate-500">api key<input type={['password','private_key','transaction_pin'].includes('api_key')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="api_key" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["api_key"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="api_token" className="text-xs font-bold uppercase tracking-wide text-slate-500">api token<input type={['password','private_key','transaction_pin'].includes('api_token')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="api_token" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["api_token"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="access_token" className="text-xs font-bold uppercase tracking-wide text-slate-500">access token<input type={['password','private_key','transaction_pin'].includes('access_token')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="access_token" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["access_token"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="public_key" className="text-xs font-bold uppercase tracking-wide text-slate-500">public key<input type={['password','private_key','transaction_pin'].includes('public_key')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="public_key" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["public_key"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="private_key" className="text-xs font-bold uppercase tracking-wide text-slate-500">private key<input type={['password','private_key','transaction_pin'].includes('private_key')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="private_key" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["private_key"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="username" className="text-xs font-bold uppercase tracking-wide text-slate-500">username<input type={['password','private_key','transaction_pin'].includes('username')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="username" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["username"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="password" className="text-xs font-bold uppercase tracking-wide text-slate-500">password<input type={['password','private_key','transaction_pin'].includes('password')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="password" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["password"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label><label key="transaction_pin" className="text-xs font-bold uppercase tracking-wide text-slate-500">transaction pin<input type={['password','private_key','transaction_pin'].includes('transaction_pin')?'password':'text'} className="mt-1 w-full rounded-xl border border-slate-300 bg-white p-3 font-normal" placeholder="transaction_pin" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}'); form.setData('credentials_json',JSON.stringify({...v,["transaction_pin"]:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}} /></label>
<label className="text-xs font-bold uppercase tracking-wide text-slate-500">API key header name<input className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="X-API-Key" onChange={e=>{try{const v=JSON.parse(form.data.credentials_json||'{}');form.setData('credentials_json',JSON.stringify({...v,api_key_name:e.target.value},null,2));form.setData('credentials_touched',true)}catch{}}}/></label>
</div>
<textarea rows={6} spellCheck={false} className="mt-3 w-full rounded-xl border border-slate-300 bg-white p-3 font-mono text-xs" value={form.data.credentials_json} onChange={e=>{form.setData('credentials_json',e.target.value); form.setData('credentials_touched',true)}} />
        {form.errors.credentials_json && <span className="mt-1 block text-red-600">{form.errors.credentials_json}</span>}
        <p className="mt-2 text-xs text-slate-500">For providers with different GET/POST authentication, use <code>headers_get</code> and <code>headers_post</code>. Example: VTpass can use <code>headers_get</code> for <code>api-key</code> + <code>public-key</code> and <code>headers_post</code> for <code>api-key</code> + <code>secret-key</code>. Do not paste credentials into the integration configuration.</p>
      </div>
      <div className="flex flex-wrap gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-700 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add provider'}</button>{editingId && <button type="button" onClick={cancelEdit} className="rounded-xl border border-slate-300 px-5 py-3 font-semibold">Cancel edit</button>}</div>
    </form>

    <section className="mt-8 space-y-4"><div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"><h2 className="text-xl font-bold text-slate-900">Registered providers ({providers.length})</h2>{selectedIds.length > 0 && <div className="flex flex-wrap gap-2"><span className="rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700">{selectedIds.length} selected</span><button disabled={bulkBusy} onClick={()=>runBulk('/admin/providers/bulk/test','post','Test all selected providers now? Each provider will remain disabled until verified.')} className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 disabled:opacity-50">Test selected</button><button disabled={bulkBusy} onClick={()=>runBulk('/admin/providers/bulk/toggle','post','Enable all selected providers that are already live-verified? Unverified providers will be skipped.',{enabled:true})} className="rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 disabled:opacity-50">Enable selected</button><button disabled={bulkBusy} onClick={()=>runBulk('/admin/providers/bulk/toggle','post','Disable all selected providers?',{enabled:false})} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-50">Disable selected</button><button disabled={bulkBusy} onClick={()=>runBulk('/admin/providers/bulk','delete','Remove all selected providers? Historical records remain, but active provider records and mappings will be disabled.',{})} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 disabled:opacity-50">Remove selected</button></div>}</div>
      {providers.length === 0 ? <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">No providers registered yet. Add a provider above using its verified documentation.</div> : providers.map(provider => <article key={provider.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="mb-3 flex items-center gap-3"><input type="checkbox" aria-label={`Select ${provider.display_name}`} checked={selectedIds.includes(provider.id)} onChange={()=>toggleSelected(provider.id)} className="h-4 w-4" /><span className="text-xs font-semibold text-slate-500">Select provider</span></div>
        <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="text-lg font-bold text-slate-900">{provider.display_name}</h3><p className="mt-1 break-all text-xs text-slate-500">{provider.identifier} · {provider.environment} · priority {provider.priority}</p><p className="mt-2 break-all text-sm text-slate-600">{provider.base_url || 'No API base URL configured'}</p></div><div className="flex flex-wrap gap-2"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{provider.verification_status}</span><span className={`rounded-full px-3 py-1 text-xs font-semibold ${provider.enabled && !provider.paused ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>{provider.enabled && !provider.paused ? 'Enabled' : 'Disabled / paused'}</span></div></div>
        <p className="mt-3 text-sm text-slate-600">Authentication: {provider.auth_type} · Integration: {provider.integration_status} · Capabilities: {provider.capabilities.join(', ') || 'none'}</p>
        {provider.last_tested_at && <p className="mt-2 text-xs text-slate-500">Last test: {provider.last_test_status || 'unknown'} · {new Date(provider.last_tested_at).toLocaleString()} · {provider.last_test_summary || 'No summary'}</p>}
        <div className="mt-4 flex flex-wrap gap-2"><button onClick={()=>router.get(`/admin/providers/${provider.id}/setup`)} className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700">Setup Wizard</button><button onClick={()=>beginEdit(provider)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Edit</button><button onClick={async()=>{try{await runJson(`/admin/providers/${provider.id}/test`,{},'POST');window.location.reload();}catch(error){window.alert(error instanceof Error?error.message:'Provider test failed.')}}} className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700">Test connection</button><button type="button" disabled={provider.enabled || provider.verification_status!=='live_verified' || provider.integration_status!=='live_verified'} title={provider.verification_status!=='live_verified'||provider.integration_status!=='live_verified' ? 'Provider must be verified before it can be enabled.' : provider.enabled ? 'Provider is already enabled.' : 'Enable provider'} onClick={async()=>{try{await runJson(`/admin/providers/${provider.id}/toggle`,{},'POST');window.location.reload();}catch(error){window.alert(error instanceof Error?error.message:'Provider status update failed.')}}} className="rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">Enable</button><button type="button" disabled={!provider.enabled} title={!provider.enabled ? 'Provider is already disabled.' : 'Disable provider'} onClick={async()=>{try{await runJson(`/admin/providers/${provider.id}/toggle`,{},'POST');window.location.reload();}catch(error){window.alert(error instanceof Error?error.message:'Provider status update failed.')}}} className="rounded-lg border border-amber-200 px-3 py-2 text-sm font-semibold text-amber-700 disabled:cursor-not-allowed disabled:opacity-40">Disable</button><button onClick={()=>remove(provider)} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Remove</button></div>
      </article>)}
    </section>
  </div></main></>;
}
