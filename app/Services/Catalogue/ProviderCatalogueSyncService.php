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
