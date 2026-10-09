import { Head, Link } from '@inertiajs/react';

type ProviderSummary = {
  id: number; identifier: string; display_name: string; verification_status: string;
  integration_status: string; enabled: boolean; paused: boolean; last_tested_at: string | null;
  last_test_status: string | null; last_successful_request_at: string | null;
  capabilities: string[]; service_categories: string[];
};
type ServiceProvider = {
  id: number; identifier: string; display_name: string; verification_status: string;
  integration_status: string; enabled: boolean; paused: boolean; mapping_enabled: boolean;
  last_tested_at: string | null; last_test_status: string | null;
};
type ServiceCoverage = {
  id: number; key: string; name: string; category: string; enabled: boolean;
  mapped_provider_count: number; live_verified_provider_count: number; providers: ServiceProvider[];
};
type Summary = {
  total_providers: number; live_verified_providers: number; production_eligible_providers: number;
  enabled_services: number; enabled_services_with_live_coverage: number; enabled_services_without_live_coverage: number;
};

const readable = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const timestamp = (value: string | null) => value ? new Date(value).toLocaleString() : 'Not recorded';

function State({ value }: { value: string }) {
  return <span className="inline-flex rounded-full border px-2 py-1 text-xs font-medium capitalize">{readable(value || 'unknown')}</span>;
}

export default function ProviderPlatformCoverage({ summary, providers, services }: { summary: Summary; providers: ProviderSummary[]; services: ServiceCoverage[] }) {
  const cards: [string, number][] = [
    ['Provider records', summary.total_providers],
    ['Live-verified providers', summary.live_verified_providers],
    ['Eligible for production routing', summary.production_eligible_providers],
    ['Enabled platform services', summary.enabled_services],
    ['Enabled services with live coverage', summary.enabled_services_with_live_coverage],
    ['Enabled services missing live coverage', summary.enabled_services_without_live_coverage],
  ];

  return (
    <>
      <Head title="API Provider Coverage" />
      <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">API Provider Coverage</h1>
            <p className="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">
              Phase A — outbound providers used by SEMIZZY ONE. Counts come from saved Core provider and mapping records; a preset or candidate is not a verified integration.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link href="/admin/provider-platform/catalogue/review" className="inline-flex w-fit items-center rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">Review provider catalogue</Link>
            <Link href="/admin/providers" className="inline-flex w-fit items-center rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">Manage Core providers</Link>
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {cards.map(([label, value]) => (
            <div key={label} className="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
              <div className="text-sm text-gray-600 dark:text-gray-300">{label}</div>
              <div className="mt-2 text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{value}</div>
            </div>
          ))}
        </div>

        <section className="overflow-hidden rounded-xl border dark:border-gray-700">
          <div className="border-b p-4 dark:border-gray-700">
            <h2 className="font-semibold text-gray-900 dark:text-gray-100">Service-by-service coverage</h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Live coverage requires an enabled mapping and a provider that is enabled, unpaused, and live-verified in both Core verification fields.</p>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y text-sm dark:divide-gray-700">
              <thead className="bg-gray-50 text-left text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <tr><th className="px-4 py-3 font-medium">Service</th><th className="px-4 py-3 font-medium">Category</th><th className="px-4 py-3 font-medium">State</th><th className="px-4 py-3 font-medium">Mapped</th><th className="px-4 py-3 font-medium">Live-verified</th><th className="px-4 py-3 font-medium">Provider details</th></tr>
              </thead>
              <tbody className="divide-y bg-white dark:divide-gray-700 dark:bg-gray-900">
                {services.map((service) => (
                  <tr key={service.id} className="align-top">
                    <td className="px-4 py-3"><div className="font-medium text-gray-900 dark:text-gray-100">{service.name}</div><div className="mt-1 font-mono text-xs text-gray-500">{service.key}</div></td>
                    <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{service.category}</td>
                    <td className="px-4 py-3"><State value={service.enabled ? 'enabled' : 'disabled'} /></td>
                    <td className="px-4 py-3 tabular-nums">{service.mapped_provider_count}</td>
                    <td className="px-4 py-3"><span className={service.live_verified_provider_count > 0 ? 'font-semibold text-green-700 dark:text-green-400' : 'font-semibold text-amber-700 dark:text-amber-400'}>{service.live_verified_provider_count}</span></td>
                    <td className="min-w-64 px-4 py-3">
                      {service.providers.length === 0 ? <span className="text-gray-500">No provider mapping recorded</span> : (
                        <div className="space-y-3">{service.providers.map((provider) => (
                          <div key={provider.id} className="space-y-1">
                            <Link href={'/admin/providers/' + provider.id + '/catalogue'} className="font-medium text-indigo-700 hover:underline dark:text-indigo-300">{provider.display_name}</Link>
                            <div className="flex flex-wrap gap-1"><State value={provider.verification_status} /><State value={provider.integration_status} />{!provider.mapping_enabled && <State value="mapping_disabled" />}{provider.paused && <State value="paused" />}</div>
                            <div className="text-xs text-gray-500">Last test: {provider.last_test_status || 'not recorded'} · {timestamp(provider.last_tested_at)}</div>
                          </div>
                        ))}</div>
                      )}
                    </td>
                  </tr>
                ))}
                {services.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">No services are registered in Core yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </section>

        <section className="overflow-hidden rounded-xl border dark:border-gray-700">
          <div className="border-b p-4 dark:border-gray-700">
            <h2 className="font-semibold text-gray-900 dark:text-gray-100">Provider verification register</h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">No latency or uptime is estimated. Only saved test timestamps, request timestamps and statuses are displayed.</p>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y text-sm dark:divide-gray-700">
              <thead className="bg-gray-50 text-left text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <tr><th className="px-4 py-3 font-medium">Provider</th><th className="px-4 py-3 font-medium">Verification</th><th className="px-4 py-3 font-medium">Integration</th><th className="px-4 py-3 font-medium">Enabled / paused</th><th className="px-4 py-3 font-medium">Capabilities recorded</th><th className="px-4 py-3 font-medium">Last successful request</th></tr>
              </thead>
              <tbody className="divide-y bg-white dark:divide-gray-700 dark:bg-gray-900">
                {providers.map((provider) => (
                  <tr key={provider.id}>
                    <td className="px-4 py-3"><Link href={'/admin/providers/' + provider.id + '/catalogue'} className="font-medium text-indigo-700 hover:underline dark:text-indigo-300">{provider.display_name}</Link><div className="mt-1 font-mono text-xs text-gray-500">{provider.identifier}</div></td>
                    <td className="px-4 py-3"><State value={provider.verification_status} /></td>
                    <td className="px-4 py-3"><State value={provider.integration_status} /></td>
                    <td className="px-4 py-3">{provider.enabled ? 'Enabled' : 'Disabled'}{provider.paused ? ' · Paused' : ''}</td>
                    <td className="px-4 py-3">{provider.capabilities.length ? <div className="flex flex-wrap gap-1">{provider.capabilities.map((capability) => <State key={capability} value={capability} />)}</div> : <span className="text-gray-500">None recorded</span>}</td>
                    <td className="px-4 py-3">{timestamp(provider.last_successful_request_at)}</td>
                  </tr>
                ))}
                {providers.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-500">No provider records have been added.</td></tr>}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </>
  );
}
