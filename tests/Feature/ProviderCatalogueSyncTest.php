<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ProviderServiceProduct;
use App\Services\Catalogue\CatalogueImportService;
use App\Services\Catalogue\ProviderCatalogueSyncService;
use App\Services\Providers\ProviderCapabilityRegistry;
use App\Services\Providers\ProviderRequestLogger;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ProviderCatalogueSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_logs_provider_response_before_importing_products(): void
    {
        $provider = $this->makeProvider();
        $category = ServiceCategory::create(['key' => 'test-category', 'name' => 'Test category']);
        $service = Service::create(['category_id' => $category->id, 'key' => 'test-service', 'name' => 'Test service', 'enabled' => true]);

        $result = new ProviderResult(true, 'ACCEPTED', data: [
            'products' => [['key' => 'bundle-1', 'name' => 'Bundle 1', 'provider_product_id' => 'provider-bundle-1', 'provider_cost' => '10.00', 'currency' => 'NGN']],
        ]);
        $adapter = Mockery::mock(RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->with($provider, 'catalogue_retrieval', ['service' => 'test-service'])->andReturn($result);
        $registry = Mockery::mock(ProviderCapabilityRegistry::class);
        $registry->shouldReceive('supports')->once()->with($provider, 'catalogue_retrieval')->andReturn(true);
        $registry->shouldReceive('validate')->once()->with($provider);
        $logger = Mockery::mock(ProviderRequestLogger::class);
        $logger->shouldReceive('record')->once()->with($provider, 'catalogue_retrieval', 'test-service', $result, Mockery::any());
        $importer = new CatalogueImportService();

        $sync = new ProviderCatalogueSyncService($adapter, $registry, $importer, $logger);

        $this->assertSame(1, $sync->sync($provider, $service));
    }



    public function test_disabled_service_cannot_trigger_catalogue_requests(): void
    {
        $provider = $this->makeProvider();
        $category = ServiceCategory::create(['key' => 'disabled-service-category', 'name' => 'Disabled service category']);
        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'disabled-service',
            'name' => 'Disabled service',
            'enabled' => false,
        ]);

        $adapter = Mockery::mock(RestJsonProviderAdapter::class);
        $adapter->shouldNotReceive('execute');
        $registry = Mockery::mock(ProviderCapabilityRegistry::class);
        $registry->shouldNotReceive('validate');
        $registry->shouldNotReceive('supports');

        $this->expectException(InvalidArgumentException::class);
        (new ProviderCatalogueSyncService(
            $adapter,
            $registry,
            new CatalogueImportService(),
            Mockery::mock(ProviderRequestLogger::class),
        ))->sync($provider, $service);
    }

    public function test_import_rechecks_provider_state_before_publishing(): void
    {
        $provider = $this->makeProvider();
        $category = ServiceCategory::create(['key' => 'recheck-category', 'name' => 'Recheck category']);
        $service = Service::create(['category_id' => $category->id, 'key' => 'recheck-service', 'name' => 'Recheck service']);
        $provider->forceFill(['enabled' => false, 'paused' => true])->save();

        $this->expectException(InvalidArgumentException::class);
        app(CatalogueImportService::class)->import($provider, $service, [[
            'key' => 'bundle-recheck',
            'name' => 'Bundle recheck',
            'provider_product_id' => 'provider-recheck',
            'provider_cost' => '10.00',
            'currency' => 'NGN',
        ]]);

        $this->assertDatabaseMissing('provider_service_products', ['provider_product_id' => 'provider-recheck']);
    }

    public function test_provider_currency_mismatch_is_not_made_sellable(): void
    {
        $provider = $this->makeProvider();
        $category = ServiceCategory::create(['key' => 'currency-category', 'name' => 'Currency category']);
        $service = Service::create(['category_id' => $category->id, 'key' => 'currency-service', 'name' => 'Currency service']);

        app(CatalogueImportService::class)->import($provider, $service, [[
            'key' => 'bundle-currency',
            'name' => 'Bundle currency',
            'provider_product_id' => 'ngn-provider',
            'provider_cost' => '10.00',
            'currency' => 'NGN',
        ]]);

        app(CatalogueImportService::class)->import($provider, $service, [[
            'key' => 'bundle-currency',
            'name' => 'Bundle currency',
            'provider_product_id' => 'usd-provider',
            'provider_cost' => '10.00',
            'currency' => 'USD',
        ]]);

        $product = $service->products()->where('key', 'bundle-currency')->firstOrFail();
        $mapping = ProviderServiceProduct::query()
            ->where('api_provider_id', $provider->id)
            ->where('service_product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('NGN', $product->currency);
        $this->assertFalse($mapping->enabled);
        $this->assertSame('USD', $mapping->currency);
    }

    public function test_malformed_provider_rows_are_skipped_and_float_costs_never_become_sellable(): void
    {
        $provider = $this->makeProvider();
        $category = ServiceCategory::create(['key' => 'malformed-row-category', 'name' => 'Malformed row category']);
        $service = Service::create(['category_id' => $category->id, 'key' => 'malformed-row-service', 'name' => 'Malformed row service']);

        $count = app(CatalogueImportService::class)->import($provider, $service, [
            [
                'key' => ['unexpected', 'array'],
                'name' => 'Malformed key row',
                'provider_product_id' => 'malformed-key',
                'provider_cost' => '10.00',
                'currency' => 'NGN',
            ],
            [
                'key' => 'float-cost-product',
                'name' => 'Float Cost Product',
                'provider_product_id' => 'float-cost-provider-product',
                'provider_cost' => 10.25,
                'currency' => 'NGN',
            ],
            [
                'key' => 'array-id-product',
                'name' => 'Array ID Product',
                'provider_product_id' => ['unexpected', 'array'],
                'provider_cost' => '10.00',
                'currency' => 'NGN',
            ],
            'not-an-object-row',
        ]);

        $this->assertSame(2, $count);
        $this->assertDatabaseMissing('service_products', [
            'service_id' => $service->id,
            'key' => 'malformed-key',
        ]);

        $product = $service->products()->where('key', 'float-cost-product')->firstOrFail();
        $mapping = ProviderServiceProduct::query()
            ->where('api_provider_id', $provider->id)
            ->where('service_product_id', $product->id)
            ->firstOrFail();

        $this->assertFalse($mapping->enabled);
        $this->assertNull($mapping->provider_cost);

        $arrayIdProduct = $service->products()->where('key', 'array-id-product')->firstOrFail();
        $arrayIdMapping = ProviderServiceProduct::query()
            ->where('api_provider_id', $provider->id)
            ->where('service_product_id', $arrayIdProduct->id)
            ->firstOrFail();

        $this->assertFalse($arrayIdMapping->enabled);
        $this->assertNull($arrayIdMapping->provider_product_id);
    }

    public function test_disabled_provider_cannot_trigger_catalogue_requests(): void
    {
        $provider = $this->makeProvider(['enabled' => false]);
        $category = ServiceCategory::create(['key' => 'disabled-category', 'name' => 'Disabled category']);
        $service = Service::create(['category_id' => $category->id, 'key' => 'disabled-service', 'name' => 'Disabled service']);

        $adapter = Mockery::mock(RestJsonProviderAdapter::class);
        $adapter->shouldNotReceive('execute');
        $registry = Mockery::mock(ProviderCapabilityRegistry::class);
        $registry->shouldNotReceive('validate');
        $registry->shouldNotReceive('supports');
        $logger = Mockery::mock(ProviderRequestLogger::class);
        $importer = new CatalogueImportService();
        $sync = new ProviderCatalogueSyncService($adapter, $registry, $importer, $logger);

        $this->expectException(InvalidArgumentException::class);
        $sync->sync($provider, $service);
    }

    private function makeProvider(array $overrides = []): ApiProvider
    {
        return ApiProvider::create(array_merge([
            'identifier' => 'catalogue-sync-provider',
            'display_name' => 'Catalogue Sync Provider',
            'enabled' => true,
            'paused' => false,
            'verification_status' => 'sandbox_verified',
            'integration_status' => 'sandbox_verified',
            'capabilities' => ['catalogue_retrieval'],
            'base_url' => 'https://provider.example.test',
            'endpoints' => ['catalogue_retrieval' => '/catalogue'],
        ], $overrides));
    }
}
