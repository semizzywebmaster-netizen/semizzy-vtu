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
                if (!is_array($item)) {
                    continue;
                }

                $rawKey = Arr::get($item, 'key');
                $rawName = Arr::get($item, 'name');
                $providerProductId = Arr::get($item, 'provider_product_id');
                $rawCost = Arr::get($item, 'provider_cost');
                $rawCurrency = Arr::get($item, 'currency', 'NGN');

                // Provider responses are untrusted input. Ignore malformed identifiers rather
                // than casting arrays/objects to strings or emitting conversion warnings.
                if ((!is_string($rawKey) && !is_int($rawKey))
                    || (!is_string($rawName) && !is_int($rawName))
                    || (!is_string($rawCurrency) && !is_int($rawCurrency))) {
                    continue;
                }

                $key = trim((string) $rawKey);
                $name = trim((string) $rawName);
                $currency = strtoupper(trim((string) $rawCurrency));

                if ($key === '' || $name === '' || !preg_match('/^[A-Z]{3}$/', $currency)) {
                    continue;
                }

                $normalizedCost = null;
                // Financial amounts must be exact decimal strings or integers; floats are not
                // accepted as precise provider costs.
                if ($rawCost !== null && (is_string($rawCost) || is_int($rawCost))) {
                    try {
                        $normalizedCost = BigDecimal::of(trim((string) $rawCost))->toScale(6, RoundingMode::UNNECESSARY);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($normalizedCost->isNegative()) {
                        continue;
                    }
                }

                $validProviderProductId = (is_string($providerProductId) || is_int($providerProductId))
                    && trim((string) $providerProductId) !== '';

                // Never let one provider product identifier point to multiple local
                // products. Reassigning an identifier silently can route future
                // purchases/reconciliation to the wrong catalogue item.
                if ($validProviderProductId) {
                    $providerProductId = trim((string) $providerProductId);
                    $conflict = ProviderServiceProduct::query()
                        ->where('api_provider_id', $provider->id)
                        ->where('provider_product_id', $providerProductId)
                        ->where('service_product_id', '!=', $product->id)
                        ->exists();

                    if ($conflict) {
                        continue;
                    }
                }

                // A provider product without a provider identifier or exact cost is retained
                // as catalogue metadata but never made sellable through this provider mapping.
                $hasSellableProviderData = $validProviderProductId && $normalizedCost !== null;

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
                    'provider_product_id' => $validProviderProductId ? (string) $providerProductId : ($existing?->provider_product_id),
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
