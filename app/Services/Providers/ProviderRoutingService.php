<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use Illuminate\Support\Collection;

final class ProviderRoutingService
{
    public function __construct(private ProviderManager $manager) {}

    /**
     * Return the ordered, service-capable provider candidates without exposing credentials.
     */
    public function candidates(string $serviceKey, string $operation = 'transaction_initiation'): Collection
    {
        return $this->manager->eligible($serviceKey, $operation)->filter(
            fn (ApiProvider $provider): bool => $this->mappingSupports($provider, $serviceKey, $operation)
        )->values();
    }

    public function mappingSupports(ApiProvider $provider, string $serviceKey, string $operation): bool
    {
        $mapping = $provider->serviceMappings()
            ->where(function ($query) use ($serviceKey): void {
                $query->whereHas('service', fn ($service) => $service->where('key', $serviceKey))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('service_id')->where('service_key', $serviceKey));
            })
            ->where('enabled', true)
            ->first();

        if (! $mapping) {
            return false;
        }

        $capabilities = $mapping->capabilities;
        return ! is_array($capabilities)
            || $capabilities === []
            || in_array($operation, $capabilities, true);
    }

    public function health(ApiProvider $provider, string $serviceKey): array
    {
        $mapping = $provider->serviceMappings()
            ->where(function ($query) use ($serviceKey): void {
                $query->whereHas('service', fn ($service) => $service->where('key', $serviceKey))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('service_id')->where('service_key', $serviceKey));
            })
            ->first();

        return [
            'provider_id' => $provider->id,
            'provider' => $provider->display_name,
            'priority' => (int) $provider->priority,
            'enabled' => (bool) $provider->enabled,
            'paused' => (bool) $provider->paused,
            'verification_status' => $provider->verification_status,
            'integration_status' => $provider->integration_status,
            'mapping_enabled' => (bool) ($mapping?->enabled ?? false),
            'mapping_capabilities' => $mapping?->capabilities ?? [],
            'last_successful_request_at' => $provider->last_successful_request_at?->toIso8601String(),
            'last_test_status' => $provider->last_test_status,
        ];
    }
}
