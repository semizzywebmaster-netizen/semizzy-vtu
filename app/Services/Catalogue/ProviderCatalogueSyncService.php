<?php

namespace App\Services\Catalogue;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Services\Providers\ProviderCapabilityRegistry;
use App\Services\Providers\ProviderRequestLogger;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class ProviderCatalogueSyncService
{
    public function __construct(
        private RestJsonProviderAdapter $adapter,
        private ProviderCapabilityRegistry $registry,
        private CatalogueImportService $importer,
        private ProviderRequestLogger $logger,
    ) {}

    public function sync(ApiProvider $provider, Service $service): int
    {
        if (! $provider->enabled || $provider->paused) {
            throw new InvalidArgumentException('Provider must be enabled and unpaused before catalogue sync.');
        }

        if (! in_array($provider->verification_status, ['sandbox_verified', 'live_verified'], true)
            || ! in_array($provider->integration_status, ['sandbox_verified', 'live_verified'], true)) {
            throw new InvalidArgumentException('Provider must pass sandbox or live verification before catalogue sync.');
        }

        if (! $service->enabled) {
            throw new InvalidArgumentException('Service must be enabled before catalogue sync.');
        }

        if (! $this->registry->supports($provider, 'catalogue_retrieval')) {
            throw new InvalidArgumentException('Provider does not support catalogue retrieval.');
        }

        $this->registry->validate($provider);
        $started = microtime(true);
        $catalogueRequest = $service->metadata['catalogue_request'] ?? ['service' => $service->key];
        if (!is_array($catalogueRequest)) {
            throw new InvalidArgumentException('Service catalogue request configuration is invalid.');
        }

        $result = $this->adapter->execute($provider, 'catalogue_retrieval', $catalogueRequest);
        $this->logger->record(
            $provider,
            'catalogue_retrieval',
            $service->key,
            $result,
            (int) round((microtime(true) - $started) * 1000),
        );

        if (!$result->accepted) {
            throw new InvalidArgumentException(
                'Catalogue retrieval failed with status: '.$result->status.'.'
            );
        }

        $items = $this->normalizeProducts($provider, $service, $result->data);
        return $this->importer->import($provider, $service, $items);
    }

    private function normalizeProducts(ApiProvider $provider, Service $service, mixed $body): array
    {
        $items = $this->extractProducts($body);
        $normalized = [];

        foreach ($items as $index => $item) {
            $key = $this->firstScalar($item, [
                'key', 'product_key', 'productKey', 'variation_code', 'variationCode',
                'variation_code', 'serviceID', 'service_id', 'serviceId', 'code', 'id',
            ]);
            $name = $this->firstScalar($item, [
                'name', 'product_name', 'productName', 'variation_name', 'variationName',
                'description', 'title', 'package', 'plan_name', 'planName',
            ]);
            $providerProductId = $this->firstScalar($item, [
                'provider_product_id', 'providerProductId', 'variation_code', 'variationCode',
                'serviceID', 'service_id', 'serviceId', 'id', 'code',
            ]);
            $cost = $this->firstScalar($item, [
                'provider_cost', 'providerCost', 'cost', 'price', 'amount',
                'selling_price', 'sellingPrice', 'unit_price', 'unitPrice',
            ]);
            $currency = $this->firstScalar($item, ['currency', 'currency_code', 'currencyCode']) ?? 'NGN';

            if ($key === null) {
                $key = $provider->identifier.':'.$service->key.':'.($providerProductId ?? (string) $index);
            }
            if ($name === null) {
                $name = $key;
            }

            if (!is_scalar($key) || !is_scalar($name) || !is_scalar($currency)) {
                continue;
            }

            $normalized[] = [
                'key' => trim((string) $key),
                'name' => trim((string) $name),
                'provider_product_id' => $providerProductId !== null && is_scalar($providerProductId)
                    ? trim((string) $providerProductId)
                    : null,
                'provider_cost' => $cost !== null && is_scalar($cost) ? trim((string) $cost) : null,
                'currency' => strtoupper(trim((string) $currency)),
                'metadata' => $item,
            ];
        }

        return $normalized;
    }

    private function firstScalar(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($item, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractProducts(mixed $body): array
    {
        if (!is_array($body)) {
            throw new InvalidArgumentException('Provider catalogue response is not a JSON object/array.');
        }

        $paths = [
            'products', 'data.products', 'items', 'data.items', 'variations',
            'data.variations', 'data', 'content', 'data.content', 'results',
            'data.results', 'services', 'data.services',
        ];

        foreach ($paths as $path) {
            $items = $path === 'data' && array_is_list($body) ? $body : Arr::get($body, $path);
            if (is_array($items)) {
                $filtered = array_values(array_filter($items, static fn(mixed $item): bool => is_array($item)));
                if ($filtered !== []) {
                    return $filtered;
                }
            }
        }

        if (array_is_list($body)) {
            return array_values(array_filter($body, static fn(mixed $item): bool => is_array($item)));
        }

        throw new InvalidArgumentException('Provider catalogue response contains no supported product collection.');
    }
}
