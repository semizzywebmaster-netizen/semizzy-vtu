<?php

namespace App\Services\Catalogue;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\ProviderServiceProduct;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CatalogueImportService
{
    public function import(ApiProvider $provider, Service $service, array $products): int
    {
        if (!$provider->exists || !$service->exists) {
            throw new InvalidArgumentException('Provider and service must exist before catalogue import.');
        }

        if (in_array($provider->integration_status, ['draft', 'test_failed'], true)
            || in_array($provider->verification_status, ['unverified', 'test_failed'], true)) {
            throw new InvalidArgumentException('Catalogue import requires a verified provider integration.');
        }

        if (!in_array('catalogue_retrieval', $provider->capabilities ?? [], true)) {
            throw new InvalidArgumentException('Provider does not declare catalogue retrieval capability.');
        }

        return DB::transaction(function () use ($provider, $service, $products): int {
            $provider = ApiProvider::query()->lockForUpdate()->findOrFail($provider->id);
            $service = Service::query()->lockForUpdate()->findOrFail($service->id);

            if (!$provider->enabled || $provider->paused
                || !in_array($provider->integration_status, ['sandbox_verified', 'live_verified'], true)
                || !in_array($provider->verification_status, ['sandbox_verified', 'live_verified'], true)) {
                throw new InvalidArgumentException('Catalogue import requires an enabled, unpaused, verified provider.');
            }

            if (!$service->enabled) {
                throw new InvalidArgumentException('Catalogue import requires an enabled service.');
            }

            if (!in_array('catalogue_retrieval', $provider->capabilities ?? [], true)) {
                throw new InvalidArgumentException('Provider does not declare catalogue retrieval capability.');
            }

            $mapping = ProviderServiceMapping::query()->firstOrCreate(
                ['api_provider_id' => $provider->id, 'service_key' => $service->key],
                ['service_id' => $service->id, 'enabled' => false]
            );

            $mapping->forceFill(['service_id' => $service->id])->save();

            $count = 0;

            foreach ($products as $item) {
                $key = trim((string) Arr::get($item, 'key', ''));
                $name = trim((string) Arr::get($item, 'name', ''));
                $providerProductId = Arr::get($item, 'provider_product_id');
                $rawCost = Arr::get($item, 'provider_cost');
                $currency = strtoupper(trim((string) Arr::get($item, 'currency', 'NGN')));

                if ($key === '' || $name === '') {
                    continue;
                }

                if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                    continue;
                }

                $normalizedCost = null;
                if ($rawCost !== null) {
                    try {
                        $normalizedCost = BigDecimal::of(trim((string) $rawCost))->toScale(6, RoundingMode::UNNECESSARY);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($normalizedCost->isNegative()) {
                        continue;
                    }
                }

                // A provider product without a provider identifier or exact cost is retained
                // as catalogue metadata but never made sellable through this provider mapping.
                $hasSellableProviderData = $providerProductId !== null
                    && trim((string) $providerProductId) !== ''
                    && $normalizedCost !== null;

                $product = ServiceProduct::query()->firstOrCreate(
                    ['service_id' => $service->id, 'key' => $key],
                    [
                        'name' => $name,
                        'currency' => $currency,
                        'enabled' => false,
                    ]
                );
                $product = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);

                $currencyCompatible = strtoupper((string) $product->currency) === $currency;
                if (!$currencyCompatible) {
                    $hasSellableProviderData = false;
                }

                $product->fill([
                    'name' => $name,
                    'metadata' => Arr::get($item, 'metadata'),
                ])->save();

                $existing = ProviderServiceProduct::query()
                    ->where('api_provider_id', $provider->id)
                    ->where('service_product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $values = [
                    'provider_product_id' => $providerProductId !== null ? (string) $providerProductId : ($existing?->provider_product_id),
                    'provider_cost' => $hasSellableProviderData ? $normalizedCost->toScale(6, RoundingMode::UNNECESSARY)->__toString() : $existing?->provider_cost,
                    'currency' => $currency,
                    'raw_catalogue' => $item,
                    'enabled' => $hasSellableProviderData,
                    'last_synced_at' => now(),
                ];

                ProviderServiceProduct::query()->updateOrCreate(
                    ['api_provider_id' => $provider->id, 'service_product_id' => $product->id],
                    $values
                );

                $count++;
            }

            return $count;
        });
    }
}
