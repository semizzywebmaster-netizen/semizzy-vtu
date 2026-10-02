<?php

namespace App\\Services\\Catalogue;

use App\\Models\\ApiProvider;
use App\\Models\\ProviderServiceMapping;
use App\\Models\\Service;
use App\\Models\\ServiceProduct;
use App\\Models\\ProviderServiceProduct;
use Illuminate\\Support\\Arr;
use Illuminate\\Support\\Facades\\DB;
use InvalidArgumentException;

final class CatalogueImportService
{
    /**
     * Import only provider-supplied catalogue records. No provider is enabled or verified by import.
     *
     * Expected product shape:
     * key, name, provider_product_id?, provider_cost?, currency?, metadata?
     */
    public function import(ApiProvider $provider, Service $service, array $products): int
    {
        if (!$provider->exists || !$service->exists) {
            throw new InvalidArgumentException('Provider and service must exist before catalogue import.');
        }

        if (!$provider->enabled || $provider->paused || $provider->integration_status === 'draft') {
            throw new InvalidArgumentException('Catalogue import is not allowed for a disabled, paused, or draft provider.');
        }

        return DB::transaction(function () use ($provider, $service, $products): int {
            $mapping = ProviderServiceMapping::query()->firstOrCreate(
                ['api_provider_id' => $provider->id, 'service_key' => $service->key],
                ['service_id' => $service->id, 'enabled' => false]
            );

            $mapping->forceFill(['service_id' => $service->id])->save();

            $count = 0;
            foreach ($products as $item) {
                $key = trim((string) Arr::get($item, 'key', ''));
                $name = trim((string) Arr::get($item, 'name', ''));
                if ($key === '' || $name === '') {
                    continue;
                }

                $product = ServiceProduct::query()->firstOrCreate(
                    ['service_id' => $service->id, 'key' => $key],
                    ['name' => $name, 'currency' => strtoupper((string) Arr::get($item, 'currency', 'NGN')), 'enabled' => false]
                );

                $product->fill([
                    'name' => $name,
                    'currency' => strtoupper((string) Arr::get($item, 'currency', $product->currency ?: 'NGN')),
                    'metadata' => Arr::get($item, 'metadata'),
                ])->save();

                ProviderServiceProduct::query()->updateOrCreate(
                    ['api_provider_id' => $provider->id, 'service_product_id' => $product->id],
                    [
                        'provider_product_id' => Arr::get($item, 'provider_product_id'),
                        'provider_cost' => Arr::get($item, 'provider_cost'),
                        'currency' => strtoupper((string) Arr::get($item, 'currency', 'NGN')),
                        'raw_catalogue' => $item,
                        'enabled' => true,
                        'last_synced_at' => now(),
                    ]
                );

                $count++;
            }

            return $count;
        });
    }
}
