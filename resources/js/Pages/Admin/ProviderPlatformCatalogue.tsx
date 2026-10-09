import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type ProviderOption = { id: number; display_name: string };
type CatalogueEntry = {
  id: number; provider_id: number; provider: string; provider_verification_status: string;
  provider_integration_status: string; provider_enabled: boolean; provider_paused: boolean;
  external_service_id: string | null; external_service_code: string | null; name: string;
  category: string | null; service_type: string | null; network: string | null;
  source_price: string | null; currency: string; source_synced_at: string | null;
  catalogue_status: string; selection_state: string | null; selected_for_review: boolean;
  approved_for_import: boolean; imported: boolean; auto_sync_allowed: boolean;
};
type Filters = { provider_id: string | number; status: string; search: string };
type PageMeta = { current_page: number; last_page: number; per_page: number; total: number };

const reviewStates = [
  ['awaiting_approval', 'Awaiting approval'], ['reviewed', 'Reviewed'],
  ['approved', 'Approved'], ['blocked', 'Blocked'], ['imported', 'Imported'],
];
const humanize = (value: string | null | undefined) =>
  (value || 'unknown').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const timestamp = (value: string | null) => value ? new Date(value).toLocaleString() : 'Not recorded';

function State({ value }: { value: string | null }) {
  return <span className="inline-flex rounded-full border px-2 py-1 text-xs font-medium">{humanize(value)}</span>;
}

export default function ProviderPlatformCatalogue({ services, meta, filters, providers, canManage, safety_note }: {
  services: CatalogueEntry[]; meta: PageMeta; filters: Filters; providers: ProviderOption[];
  canManage: boolean; safety_note: string;
}) {
  const [providerId, setProviderId] = useState(String(filters.provider_id || ''));
  const [status, setStatus] = useState(filters.status || '');
  const [search, setSearch] = useState(filters.search || '');

  const applyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    router.get('/admin/provider-platform/catalogue/review', {
      provider_id: providerId || undefined, status: status || undefined, search: search || undefined,
    }, { preserveState: true, replace: true });
  };

  const selectForReview = (entry: CatalogueEntry) => {
    router.post('/admin/provider-platform/catalogue/' + entry.id + '/select', { selection_scope: 'product' }, {
      preserveScroll: true,
      onSuccess: () => router.reload({ only: ['services', 'meta', 'filters'] }),
    });
  };

  return (
    <>
      <Head title="Provider Catalogue Review" />
      <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Provider Catalogue Review</h1>
            <p className="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">Review provider-sourced catalogue records before any product import or mapping decision.</p>
          </div>
          <Link href="/admin/provider-platform" className="inline-flex w-fit rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">Back to coverage</Link>
        </div>

        <div className="rounded-xl border p-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
          <strong>Safety boundary:</strong> {safety_note} Source prices are informational until their provider, currency, timestamp and product identifiers are verified.
        </div>

        <form onSubmit={applyFilters} className="grid gap-3 rounded-xl border p-4 dark:border-gray-700 sm:grid-cols-2 xl:grid-cols-4">
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Provider</span>
            <select className="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" value={providerId} onChange={(event) => setProviderId(event.target.value)}>
              <option value="">All providers</option>
              {providers.map((provider) => <option key={provider.id} value={provider.id}>{provider.display_name}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium">Review state</span>
            <select className="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" value={status} onChange={(event) => setStatus(event.target.value)}>
              <option value="">All states</option>
              {reviewStates.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm sm:col-span-2">
            <span className="font-medium">Search name or provider service ID/code</span>
            <input className="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" value={search} maxLength={120} onChange={(event) => setSearch(event.target.value)} placeholder="Search catalogue records" />
          </label>
          <div className="sm:col-span-2 xl:col-span-4">
            <button type="submit" className="rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-800">Apply filters</button>
          </div>
        </form>

        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
          <span>{meta.total} catalogue record(s)</span><span>Page {meta.current_page} of {Math.max(meta.last_page, 1)}</span>
        </div>

        <div className="overflow-x-auto rounded-xl border dark:border-gray-700">
          <table className="min-w-full divide-y text-left text-sm dark:divide-gray-700">
            <thead className="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
              <tr><th className="px-4 py-3 font-medium">Provider / service</th><th className="px-4 py-3 font-medium">Source identifiers</th><th className="px-4 py-3 font-medium">Source price</th><th className="px-4 py-3 font-medium">Provider state</th><th className="px-4 py-3 font-medium">Review / import</th><th className="px-4 py-3 font-medium">Action</th></tr>
            </thead>
            <tbody className="divide-y dark:divide-gray-700">
              {services.map((entry) => (
                <tr key={entry.id} className="align-top">
                  <td className="space-y-1 px-4 py-3">
                    <div className="font-semibold text-gray-900 dark:text-gray-100">{entry.name}</div><div>{entry.provider}</div>
                    <div className="text-xs text-gray-500">{entry.category || 'Uncategorised'}{entry.service_type ? ' · ' + entry.service_type : ''}{entry.network ? ' · ' + entry.network : ''}</div>
                  </td>
                  <td className="space-y-1 px-4 py-3">
                    <div>External ID: <code>{entry.external_service_id || 'Not recorded'}</code></div><div>Code: <code>{entry.external_service_code || 'Not recorded'}</code></div>
                    <div className="text-xs text-gray-500">Catalogue state: {humanize(entry.catalogue_status)}</div>
                  </td>
                  <td className="space-y-1 px-4 py-3">
                    <div className="font-medium">{entry.source_price ?? 'Not recorded'} {entry.currency}</div><div className="text-xs text-gray-500">Last source sync: {timestamp(entry.source_synced_at)}</div>
                  </td>
                  <td className="space-y-1 px-4 py-3">
                    <State value={entry.provider_verification_status} /><State value={entry.provider_integration_status} />
                    <div className="text-xs">{entry.provider_enabled ? 'Enabled' : 'Disabled'}{entry.provider_paused ? ' · Paused' : ''}</div>
                  </td>
                  <td className="space-y-1 px-4 py-3">
                    <State value={entry.selection_state || 'not_selected'} />
                    <div className="text-xs">{entry.approved_for_import ? 'Approved' : 'Not approved'} · {entry.imported ? 'Imported' : 'Not imported'}</div>
                    <div className="text-xs">{entry.auto_sync_allowed ? 'Auto-sync allowed' : 'Auto-sync off'}</div>
                  </td>
                  <td className="px-4 py-3">
                    {entry.selected_for_review ? <span className="text-xs text-gray-500">Already in review queue</span>
                      : canManage ? <button type="button" onClick={() => selectForReview(entry)} className="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800">Add to review</button>
                      : <span className="text-xs text-gray-500">Admin action required</span>}
                  </td>
                </tr>
              ))}
              {services.length === 0 && <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-500">No provider catalogue entries match these filters. An empty result does not imply that provider discovery or live sync has been performed.</td></tr>}
            </tbody>
          </table>
        </div>

        <div className="flex items-center justify-between gap-3">
          {meta.current_page > 1 ? <Link className="rounded-lg border px-4 py-2 text-sm" href={'/admin/provider-platform/catalogue/review?page=' + (meta.current_page - 1) + (providerId ? '&provider_id=' + providerId : '') + (status ? '&status=' + status : '') + (search ? '&search=' + encodeURIComponent(search) : '')}>Previous</Link> : <span />}
          {meta.current_page < meta.last_page ? <Link className="rounded-lg border px-4 py-2 text-sm" href={'/admin/provider-platform/catalogue/review?page=' + (meta.current_page + 1) + (providerId ? '&provider_id=' + providerId : '') + (status ? '&status=' + status : '') + (search ? '&search=' + encodeURIComponent(search) : '')}>Next</Link> : <span />}
        </div>
      </div>
    </>
  );
}
