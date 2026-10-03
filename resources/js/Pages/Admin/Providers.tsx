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
  auth_type: 'custom' | 'bearer' | 'basic' | 'api_key_header';
  priority: number;
  integration_config: string;
};

const emptyForm: FormData = {
  identifier: '',
  display_name: '',
  base_url: '',
  documentation_url: '',
  official_website: '',
  environment: 'sandbox',
  auth_type: 'bearer',
  priority: 100,
  integration_config: JSON.stringify({ capabilities: ['health'], endpoints: {}, service_categories: [], credentials: {} }, null, 2),
};

export default function Providers({ providers }: { providers: Provider[] }) {
  const [editingId, setEditingId] = useState<number | null>(null);
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
    };

    if (editingId) {
      router.patch(`/admin/providers/${editingId}`, payload, { preserveScroll: true, onSuccess: cancelEdit });
    } else {
      router.post('/admin/providers', payload, { preserveScroll: true, onSuccess: () => { form.reset(); form.setData(emptyForm); } });
    }
  };

  const remove = (provider: Provider) => {
    if (window.confirm(`Remove ${provider.display_name} from the active provider registry? Historical records will be retained.`)) {
      router.delete(`/admin/providers/${provider.id}`, { preserveScroll: true });
    }
  };

  return <><Head title="API Providers" /><main className="min-h-screen bg-slate-50 p-4 md:p-8"><div className="mx-auto max-w-6xl">
    <Link href="/dashboard" className="text-sm font-semibold text-indigo-700">← Dashboard</Link>
    <header className="mt-3"><h1 className="text-3xl font-extrabold text-slate-900">API Providers</h1><p className="mt-2 text-slate-600">Add as many providers as needed. New providers are always saved disabled and unverified; test and verify real credentials before enabling.</p></header>

    <form onSubmit={submit} className="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h2 className="text-lg font-bold">{editingId ? 'Edit provider configuration' : 'Add provider'}</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        {!editingId && <label className="block text-sm font-semibold">Unique identifier<input required maxLength={100} pattern="[A-Za-z0-9_-]+" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="provider-name" value={form.data.identifier} onChange={e=>form.setData('identifier',e.target.value)} />{form.errors.identifier && <span className="mt-1 block text-red-600">{form.errors.identifier}</span>}</label>}
        <label className="block text-sm font-semibold">Display name<input required maxLength={160} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="Provider display name" value={form.data.display_name} onChange={e=>form.setData('display_name',e.target.value)} />{form.errors.display_name && <span className="mt-1 block text-red-600">{form.errors.display_name}</span>}</label>
        <label className="block text-sm font-semibold">Base API URL<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" placeholder="https://api.example.com" value={form.data.base_url} onChange={e=>form.setData('base_url',e.target.value)} />{form.errors.base_url && <span className="mt-1 block text-red-600">{form.errors.base_url}</span>}</label>
        <label className="block text-sm font-semibold">API documentation URL<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.documentation_url} onChange={e=>form.setData('documentation_url',e.target.value)} /></label>
        <label className="block text-sm font-semibold">Official website<input type="url" className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.official_website} onChange={e=>form.setData('official_website',e.target.value)} /></label>
        <label className="block text-sm font-semibold">Environment<select className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.environment} onChange={e=>form.setData('environment',e.target.value as FormData['environment'])}><option value="sandbox">Sandbox</option><option value="production">Production</option></select></label>
        <label className="block text-sm font-semibold">Authentication type<select className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.auth_type} onChange={e=>form.setData('auth_type',e.target.value as FormData['auth_type'])}><option value="bearer">Bearer token</option><option value="api_key_header">API key header</option><option value="basic">HTTP Basic</option><option value="custom">Custom</option></select></label>
        <label className="block text-sm font-semibold">Priority (lower is earlier)<input type="number" min={0} max={100000} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-normal" value={form.data.priority} onChange={e=>form.setData('priority',Number(e.target.value))} /></label>
      </div>
      <label className="block text-sm font-semibold">Capabilities, endpoints, service categories and credentials (JSON)<textarea rows={8} spellCheck={false} className="mt-1 w-full rounded-xl border border-slate-300 p-3 font-mono text-xs font-normal" value={form.data.integration_config} onChange={e=>form.setData('integration_config',e.target.value)} />{form.errors.integration_config && <span className="mt-1 block text-red-600">{form.errors.integration_config}</span>}<span className="mt-1 block text-xs font-normal text-slate-500">Use only verified provider documentation. Credentials are encrypted at rest and never shown again in this form. When editing, existing credentials are intentionally omitted from the prefilled JSON; add a credentials object only when rotating them.</span></label>
      <div className="flex flex-wrap gap-3"><button disabled={form.processing} className="rounded-xl bg-indigo-700 px-5 py-3 font-semibold text-white disabled:opacity-50">{form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add provider'}</button>{editingId && <button type="button" onClick={cancelEdit} className="rounded-xl border border-slate-300 px-5 py-3 font-semibold">Cancel edit</button>}</div>
    </form>

    <section className="mt-8 space-y-4"><h2 className="text-xl font-bold text-slate-900">Registered providers ({providers.length})</h2>
      {providers.length === 0 ? <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">No providers registered yet. Add a provider above using its verified documentation.</div> : providers.map(provider => <article key={provider.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="text-lg font-bold text-slate-900">{provider.display_name}</h3><p className="mt-1 break-all text-xs text-slate-500">{provider.identifier} · {provider.environment} · priority {provider.priority}</p><p className="mt-2 break-all text-sm text-slate-600">{provider.base_url || 'No API base URL configured'}</p></div><div className="flex flex-wrap gap-2"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{provider.verification_status}</span><span className={`rounded-full px-3 py-1 text-xs font-semibold ${provider.enabled && !provider.paused ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>{provider.enabled && !provider.paused ? 'Enabled' : 'Disabled / paused'}</span></div></div>
        <p className="mt-3 text-sm text-slate-600">Authentication: {provider.auth_type} · Integration: {provider.integration_status} · Capabilities: {provider.capabilities.join(', ') || 'none'}</p>
        {provider.last_tested_at && <p className="mt-2 text-xs text-slate-500">Last test: {provider.last_test_status || 'unknown'} · {new Date(provider.last_tested_at).toLocaleString()} · {provider.last_test_summary || 'No summary'}</p>}
        <div className="mt-4 flex flex-wrap gap-2"><button onClick={()=>beginEdit(provider)} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Edit</button><button onClick={()=>router.post(`/admin/providers/${provider.id}/test`, {}, {preserveScroll:true})} className="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700">Test connection</button>{provider.verification_status==='live_verified'&&provider.integration_status==='live_verified'&&<button onClick={()=>router.post(`/admin/providers/${provider.id}/toggle`, {}, {preserveScroll:true})} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">{provider.enabled?'Disable':'Enable'}</button>}<button onClick={()=>remove(provider)} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Remove</button></div>
      </article>)}
    </section>
  </div></main></>;
}
