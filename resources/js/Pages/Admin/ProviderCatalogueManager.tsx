import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

type Provider = {
  id: number;
  identifier: string;
  display_name: string;
  verification_status: string;
  integration_status: string;
  enabled: boolean;
  paused: boolean;
  service_categories: string[];
  capabilities: string[];
};

type PlatformService = { id: number; key: string; name: string; category: string; products: { id: number; key: string; name: string; publication_status?: string }[] };

type CatalogueRow = {
  id: number;
  provider_service_id: number;
  imported: boolean;
  approved: boolean;
  auto_sync_allowed: boolean;
  state: string;
  category: string | null;
  subcategory: string | null;
  service: {
    id: number;
    external_service_id: string;
    external_service_code: string | null;
    name: string;
    description: string | null;
    service_type: string | null;
    network: string | null;
    provider_price: string | number | null;
    currency: string;
    status: string;
    last_synced_at?: string | null;
  } | null;
};

export default function ProviderCatalogueManager({ provider, platformServices, canMapProducts = false }: { provider: Provider; platformServices: PlatformService[]; canMapProducts?: boolean }) {
  const [rows, setRows] = useState<CatalogueRow[]>([]);
  const [category, setCategory] = useState('all');
  const [selected, setSelected] = useState<number[]>([]);
  const [autoSync, setAutoSync] = useState(false);
  const [platformServiceId, setPlatformServiceId] = useState('');
  const [rowTargetServices, setRowTargetServices] = useState<Record<number, string>>({});
  const [rowTargetProducts, setRowTargetProducts] = useState<Record<number, string>>({});
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const csrfToken = () => {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (meta) return meta;
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
  };

  const requestJson = async (url: string, data: Record<string, unknown> = {}) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(data),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || `Request failed (${response.status})`);
    return result;
  };

  const refresh = async () => {
    const response = await fetch(`/admin/providers/${provider.id}/provider-services/import-preview`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.message || `Could not load provider catalogue (${response.status})`);
    setRows(Array.isArray(result.data) ? result.data : []);
  };

  useEffect(() => {
    refresh().catch((reason) => setError(reason instanceof Error ? reason.message : 'Could not load provider catalogue.'));
  }, [provider.id]);

  const categories = useMemo(() => {
    const values = rows.map((row) => row.category?.trim()).filter((value): value is string => Boolean(value));
    return [...new Set(values)].sort((a, b) => a.localeCompare(b));
  }, [rows]);

  const filtered = useMemo(
    () => rows.filter((row) => category === 'all' || (category === 'uncategorised' ? !row.category : row.category === category)),
    [rows, category],
  );

  const visibleIds = filtered.map((row) => row.provider_service_id);
  const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => selected.includes(id));

  const syncCatalogue = async () => {
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const result = await requestJson(`/admin/providers/${provider.id}/sync`);
      if (result.status !== 'success') throw new Error(result.message || 'Catalogue refresh did not complete.');
      const summary = result.summary || {};
      setMessage(`Catalogue refreshed. Found ${summary.discovered ?? 0} services; ${summary.new ?? 0} new; ${summary.price_changed ?? 0} source-price changes; ${summary.pending_approval ?? 0} awaiting approval. Selling prices were not changed by this refresh.`);
      await refresh();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Provider catalogue refresh failed.');
    } finally {
      setBusy(false);
    }
  };

  const syncPlatformService = async () => {
    if (!platformServiceId) return;
    const service = platformServices.find((item) => String(item.id) === platformServiceId);
    if (!service) return;
    if (!window.confirm(`Sync ${provider.display_name} catalogue and source prices for ${service.category} → ${service.name}? The provider must be eligible for catalogue sync. Customer selling prices remain governed by the platform pricing rules.`)) return;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const result = await requestJson('/admin/catalogue/sync', {
        api_provider_id: provider.id,
        service_id: Number(platformServiceId),
      });
      setMessage(result.message || `Catalogue sync completed for ${service.name}. Processed ${result.products_processed ?? 0} product record(s).`);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Platform catalogue sync failed.');
    } finally {
      setBusy(false);
    }
  };

  const importSelected = async () => {
    if (!selected.length) return;
    if (!window.confirm(`Approve and import/update ${selected.length} selected provider service(s)? This imports provider catalogue entries; it does not change SEMIZZY ONE selling prices or enable provider routing.`)) return;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const approved = await requestJson(`/admin/providers/${provider.id}/provider-services/approve`, {
        provider_service_ids: selected,
        auto_sync_allowed: autoSync,
      });
      const imported = await requestJson(`/admin/providers/${provider.id}/provider-services/import`, {
        provider_service_ids: selected,
      });
      setMessage(`Approved ${approved.approved ?? 0}; imported/updated ${imported.imported ?? 0}; skipped ${imported.skipped_unapproved ?? 0}.`);
      setSelected([]);
      await refresh();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Selected services could not be imported.');
    } finally {
      setBusy(false);
    }
  };

  const mapRowToMyServices = async (row: CatalogueRow) => {
    const serviceId = rowTargetServices[row.provider_service_id] || platformServiceId;
    if (!serviceId) {
      setError('Choose the platform service that this provider catalogue row belongs to.');
      return;
    }
    if (!row.approved || !row.imported) {
      setError('Approve and import this provider catalogue row before mapping it to My Services.');
      return;
    }

    setBusy(true);
    setError('');
    setMessage('');
    try {
      const productId = rowTargetProducts[row.provider_service_id];
      const result = await requestJson(`/admin/providers/${provider.id}/provider-services/${row.provider_service_id}/map-to-platform`, {
        service_id: Number(serviceId),
        ...(productId ? { service_product_id: Number(productId) } : {}),
      });
      setMessage(`${result.product?.name || row.service?.name || 'Provider product'} saved as a draft. Configure tier prices and verify routing before publishing.`);
      await refresh();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Provider product could not be mapped.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <Head title={`${provider.display_name} — Services & Prices`} />
      <main className="min-h-screen bg-slate-50 p-4 md:p-8">
        <div className="mx-auto max-w-7xl">
          <Link href="/admin/providers" className="text-sm font-semibold text-indigo-700">← All API providers</Link>
          <header className="mt-4 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
              <h1 className="text-2xl font-extrabold text-slate-900 md:text-3xl">{provider.display_name}: Services & Prices</h1>
              <p className="mt-2 max-w-3xl text-sm text-slate-600">Refresh this provider’s documented catalogue, review services and source prices by category, then select which entries to import. Source cost is kept separate from your customer selling prices.</p>
              <p className="mt-2 text-xs text-slate-500">{provider.identifier} · {provider.verification_status} · {provider.integration_status}</p>
            </div>
            <button type="button" disabled={busy} onClick={syncCatalogue} className="rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white disabled:opacity-50">
              {busy ? 'Working…' : 'Refresh services & source prices'}
            </button>
          </header>

          {message && <div role="status" className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{message}</div>}
          {error && <div role="alert" className="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</div>}

          <section className="mt-6 grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase text-slate-500">Discovered entries</p><p className="mt-1 text-2xl font-bold text-slate-900">{rows.length}</p></div>
            <div className="rounded-xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase text-slate-500">Imported entries</p><p className="mt-1 text-2xl font-bold text-slate-900">{rows.filter((row) => row.imported).length}</p></div>
            <div className="rounded-xl border border-slate-200 bg-white p-4"><p className="text-xs font-semibold uppercase text-slate-500">Awaiting approval</p><p className="mt-1 text-2xl font-bold text-slate-900">{rows.filter((row) => !row.approved).length}</p></div>
          </section>

          <section className="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 md:p-5">
            <h2 className="text-base font-bold text-slate-900">Sync prices into a platform service category</h2>
            <p className="mt-1 max-w-3xl text-sm text-slate-600">This uses the Core catalogue importer for a specific platform service, so provider product costs can be refreshed against the existing service catalogue. Only providers that pass Core eligibility and capability checks can complete the sync.</p>
            <div className="mt-3 flex flex-col gap-3 sm:flex-row">
              <select value={platformServiceId} onChange={(event) => setPlatformServiceId(event.target.value)} className="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white p-3 text-sm" aria-label="Platform service category">
                <option value="">Choose platform service category / service…</option>
                {platformServices.map((service) => <option key={service.id} value={service.id}>{service.category} — {service.name} ({service.key})</option>)}
              </select>
              <button type="button" disabled={!platformServiceId || busy} onClick={syncPlatformService} className="rounded-xl border border-indigo-300 bg-white px-4 py-3 text-sm font-bold text-indigo-800 disabled:opacity-50">{busy ? 'Working…' : 'Sync selected category prices'}</button>
            </div>
          </section>

          <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <label className="block text-sm font-semibold text-slate-800">Filter by service category
                <select value={category} onChange={(event) => setCategory(event.target.value)} className="mt-1 block w-full min-w-64 rounded-xl border border-slate-300 bg-white p-3 font-normal">
                  <option value="all">All discovered categories</option>
                  {categories.map((item) => <option key={item} value={item}>{item}</option>)}
                  <option value="uncategorised">Uncategorised / needs mapping</option>
                </select>
              </label>
              <div className="flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" checked={autoSync} onChange={(event) => setAutoSync(event.target.checked)} />Allow approved items to auto-sync source catalogue later</label>
                <button type="button" disabled={!visibleIds.length || busy} onClick={() => setSelected(allVisibleSelected ? selected.filter((id) => !visibleIds.includes(id)) : [...new Set([...selected, ...visibleIds])])} className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-50">{allVisibleSelected ? 'Clear visible selection' : 'Select visible category'}</button>
                <button type="button" disabled={!selected.length || busy} onClick={importSelected} className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">{busy ? 'Working…' : `Approve & import/update selected (${selected.length})`}</button>
              </div>
            </div>

            <div className="mt-4 overflow-x-auto">
              <table className="w-full min-w-[1280px] border-collapse text-left text-sm">
                <thead><tr className="border-b border-slate-200 text-xs uppercase text-slate-500"><th className="p-3">Select</th><th className="p-3">Service / product</th><th className="p-3">Category</th><th className="p-3">Provider ID / code</th><th className="p-3">Source price</th><th className="p-3">Availability</th><th className="p-3">Import state</th><th className="p-3">Last synced</th><th className="p-3">Map to My Services</th></tr></thead>
                <tbody>
                  {filtered.map((row) => <tr key={row.provider_service_id} className="border-b border-slate-100 align-top hover:bg-slate-50">
                    <td className="p-3"><input aria-label={`Select ${row.service?.name || 'provider service'}`} type="checkbox" checked={selected.includes(row.provider_service_id)} onChange={() => setSelected((ids) => ids.includes(row.provider_service_id) ? ids.filter((id) => id !== row.provider_service_id) : [...ids, row.provider_service_id])} /></td>
                    <td className="p-3"><p className="font-semibold text-slate-900">{row.service?.name || 'Unknown service'}</p><p className="mt-1 text-xs text-slate-500">{row.service?.description || row.service?.network || row.service?.service_type || 'No additional details supplied'}</p></td>
                    <td className="p-3">{row.category || 'Uncategorised'}{row.subcategory ? <p className="mt-1 text-xs text-slate-500">{row.subcategory}</p> : null}</td>
                    <td className="p-3 font-mono text-xs">{row.service?.external_service_id || '—'}{row.service?.external_service_code ? <p className="mt-1">{row.service.external_service_code}</p> : null}</td>
                    <td className="p-3 font-semibold tabular-nums">{row.service?.provider_price == null ? 'Not supplied' : `${row.service.currency || 'NGN'} ${Number(row.service.provider_price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`}</td>
                    <td className="p-3">{row.service?.status || 'Unknown'}</td>
                    <td className="p-3"><span className={row.imported ? 'text-emerald-700' : row.approved ? 'text-indigo-700' : 'text-amber-700'}>{row.imported ? 'Imported' : row.approved ? 'Approved, not imported' : 'Needs approval'}</span>{row.auto_sync_allowed ? <p className="mt-1 text-xs text-slate-500">Auto-sync allowed</p> : null}</td>
                    <td className="p-3 text-xs text-slate-500">{row.service?.last_synced_at ? new Date(row.service.last_synced_at).toLocaleString() : 'Not recorded'}</td>
                    <td className="min-w-72 p-3">
                      <div className="space-y-2">
                        <select aria-label={`Platform service for ${row.service?.name || 'provider product'}`} value={rowTargetServices[row.provider_service_id] ?? platformServiceId} onChange={(event) => { setRowTargetServices((current) => ({ ...current, [row.provider_service_id]: event.target.value })); setRowTargetProducts((current) => ({ ...current, [row.provider_service_id]: '' })); }} className="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs">
                          <option value="">Choose platform service</option>
                          {platformServices.map((service) => <option key={service.id} value={service.id}>{service.category} — {service.name}</option>)}
                        </select>
                        <select aria-label={`Draft product variant for ${row.service?.name || 'provider product'}`} value={rowTargetProducts[row.provider_service_id] ?? ''} onChange={(event) => setRowTargetProducts((current) => ({ ...current, [row.provider_service_id]: event.target.value }))} className="w-full rounded-lg border border-slate-300 bg-white p-2 text-xs" disabled={!(rowTargetServices[row.provider_service_id] || platformServiceId)}>
                          <option value="">Create new draft variant</option>
                          {(platformServices.find((service) => String(service.id) === (rowTargetServices[row.provider_service_id] || platformServiceId))?.products ?? []).map((product) => <option key={product.id} value={product.id}>{product.name} ({product.key})</option>)}
                        </select>
                        <button type="button" disabled={!canMapProducts || busy || !row.approved || !row.imported || !(rowTargetServices[row.provider_service_id] || platformServiceId)} onClick={() => void mapRowToMyServices(row)} className="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-800 disabled:cursor-not-allowed disabled:opacity-50">{!canMapProducts ? 'Catalogue permission required' : row.approved && row.imported ? 'Map as Draft' : 'Approve & import first'}</button>
                        <p className="text-[11px] leading-4 text-slate-500">Creates a disabled draft mapping only. It never publishes or enables routing.</p>
                      </div>
                    </td>
                  </tr>)}
                  {filtered.length === 0 && <tr><td colSpan={9} className="p-8 text-center text-slate-500">No discovered services in this category yet. Refresh the catalogue if this provider officially supports service discovery.</td></tr>}
                </tbody>
              </table>
            </div>
            <p className="mt-4 text-xs leading-5 text-slate-500">Important: refreshing updates discovered provider catalogue/source-price data where the provider API exposes it. Importing does not activate routing, publish products, or overwrite SEMIZZY ONE selling prices. Providers without a documented catalogue endpoint must be configured for a validated manual mapping process; no prices or product IDs should be guessed.</p>
          </section>
        </div>
      </main>
    </>
  );
}
