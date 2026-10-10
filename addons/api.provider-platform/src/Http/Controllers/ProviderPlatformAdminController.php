<?php

namespace Semizzy\Addons\ApiProviderPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use Inertia\Inertia;
use Inertia\Response;

final class ProviderPlatformAdminController extends Controller
{
    public function index(): Response
    {
        $providerColumns = [
            'id', 'identifier', 'display_name', 'verification_status', 'integration_status',
            'enabled', 'paused', 'last_tested_at', 'last_test_status',
            'last_successful_request_at', 'capabilities', 'service_categories',
        ];

        $providers = ApiProvider::query()
            ->orderBy('display_name')
            ->get($providerColumns)
            ->map(fn (ApiProvider $provider): array => $this->providerSummary($provider))
            ->values();

        $services = Service::query()->with('category')->orderBy('name')->get();
        $mappings = ProviderServiceMapping::query()
            ->with(['provider:' . implode(',', $providerColumns)])
            ->get();

        $serviceRows = $services->map(function (Service $service) use ($mappings): array {
            $related = $mappings->filter(function (ProviderServiceMapping $mapping) use ($service): bool {
                if ($mapping->service_id !== null) {
                    return (int) $mapping->service_id === (int) $service->id;
                }

                return (string) $mapping->service_key === (string) $service->key;
            });

            $mappedProviders = $related
                ->filter(fn (ProviderServiceMapping $mapping): bool => $mapping->provider !== null)
                ->unique('api_provider_id')
                ->values();

            $liveProviders = $related->filter(function (ProviderServiceMapping $mapping): bool {
                $provider = $mapping->provider;

                return $provider !== null
                    && $mapping->enabled
                    && $provider->enabled
                    && !$provider->paused
                    && $provider->verification_status === 'live_verified'
                    && $provider->integration_status === 'live_verified';
            })->unique('api_provider_id')->values();

            return [
                'id' => $service->id,
                'key' => $service->key,
                'name' => $service->name,
                'category' => $service->category?->name ?? 'Uncategorised',
                'enabled' => (bool) $service->enabled,
                'mapped_provider_count' => $mappedProviders->count(),
                'live_verified_provider_count' => $liveProviders->count(),
                'providers' => $mappedProviders->map(function (ProviderServiceMapping $mapping): array {
                    $provider = $mapping->provider;

                    return [
                        'id' => $provider->id,
                        'identifier' => $provider->identifier,
                        'display_name' => $provider->display_name,
                        'verification_status' => $provider->verification_status,
                        'integration_status' => $provider->integration_status,
                        'enabled' => (bool) $provider->enabled,
                        'paused' => (bool) $provider->paused,
                        'mapping_enabled' => (bool) $mapping->enabled,
                        'last_tested_at' => $provider->last_tested_at?->toISOString(),
                        'last_test_status' => $provider->last_test_status,
                    ];
                })->values()->all(),
            ];
        })->values();

        $liveVerifiedProviders = $providers->filter(
            fn (array $provider): bool => $provider['verification_status'] === 'live_verified'
                && $provider['integration_status'] === 'live_verified'
        )->count();

        $productionEligibleProviders = $providers->filter(
            fn (array $provider): bool => $provider['verification_status'] === 'live_verified'
                && $provider['integration_status'] === 'live_verified'
                && $provider['enabled']
                && !$provider['paused']
        )->count();

        $enabledServices = $serviceRows->filter(fn (array $service): bool => $service['enabled']);
        $coveredEnabledServices = $enabledServices->filter(
            fn (array $service): bool => $service['live_verified_provider_count'] > 0
        )->count();

        return Inertia::render('Admin/ProviderPlatformCoverage', [
            'summary' => [
                'total_providers' => $providers->count(),
                'live_verified_providers' => $liveVerifiedProviders,
                'production_eligible_providers' => $productionEligibleProviders,
                'enabled_services' => $enabledServices->count(),
                'enabled_services_with_live_coverage' => $coveredEnabledServices,
                'enabled_services_without_live_coverage' => $enabledServices->count() - $coveredEnabledServices,
            ],
            'providers' => $providers,
            'services' => $serviceRows,
        ]);
    }

    private function providerSummary(ApiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'identifier' => $provider->identifier,
            'display_name' => $provider->display_name,
            'verification_status' => $provider->verification_status,
            'integration_status' => $provider->integration_status,
            'enabled' => (bool) $provider->enabled,
            'paused' => (bool) $provider->paused,
            'last_tested_at' => $provider->last_tested_at?->toISOString(),
            'last_test_status' => $provider->last_test_status,
            'last_successful_request_at' => $provider->last_successful_request_at?->toISOString(),
            'capabilities' => array_values(array_filter($provider->capabilities ?? [], 'is_string')),
            'service_categories' => array_values(array_filter($provider->service_categories ?? [], 'is_string')),
        ];
    }
}
