<?php

namespace App\\Services\\Catalogue;

use App\\Models\\ApiProvider;
use App\\Models\\Service;
use App\\Services\\Providers\\ProviderCapabilityRegistry;
use App\\Services\\Providers\\RestJsonProviderAdapter;
use Illuminate\\Support\\Arr;
use InvalidArgumentException;

final class ProviderCatalogueSyncService
{
    public function __construct(
        private RestJsonProviderAdapter $adapter,
        private ProviderCapabilityRegistry $registry,
        private CatalogueImportService $importer,
    ) {}

    public function sync(ApiProvider $provider, Service $service): int
    {
        $this->registry->validate($provider);

        if (!in_array('catalogue_retrieval', $provider->capabilities ?? [], true)) {
            throw new InvalidArgumentException('Provider does not support catalogue retrieval.');
        }

        $result=$this->adapter->execute($provider, 'catalogue_retrieval', [
            'service' => $service->key,
        ]);

        if (!$result->accepted) {
            throw new InvalidArgumentException(
                'Catalogue retrieval failed: '.($result->message ?: $result->status)
            );
        }

        $items=$this->extractProducts($result->data);
        return $this->importer->import($provider, $service, $items);
    }

    private function extractProducts(mixed $body): array
    {
        if (!is_array($body)) {
            throw new InvalidArgumentException('Provider catalogue response is not a JSON object/array.');
        }

        $items=Arr::get($body, 'products');
        if (!is_array($items)) $items=Arr::get($body, 'data.products');
        if (!is_array($items)) $items=Arr::get($body, 'items');
        if (!is_array($items) && array_is_list($body)) $items=$body;

        if (!is_array($items)) {
            throw new InvalidArgumentException('Provider catalogue response contains no supported product collection.');
        }

        return array_values(array_filter($items, static fn(mixed $item): bool => is_array($item)));
    }
}
