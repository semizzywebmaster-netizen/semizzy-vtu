<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceCategory;
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
        $service = Service::create(['category_id' => $category->id, 'key' => 'test-service', 'name' => 'Test service']);

        $result = new ProviderResult(true, 'ACCEPTED', data: [
            'products' => [['key' => 'bundle-1', 'name' => 'Bundle 1', 'provider_product_id' => 'provider-bundle-1', 'provider_cost' => '10.00', 'currency' => 'NGN']],
        ]);
        $adapter = Mockery::mock(RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->with($provider, 'catalogue_retrieval', ['service' => 'test-service'])->andReturn($result);
        $registry = Mockery::mock(ProviderCapabilityRegistry::class);
        $registry->shouldReceive('supports')->once()->with($provider, 'catalogue_retrieval')->andReturn(true);
        $registry->shouldReceive('validate')->once()->with($provider);
        $logger = Mockery::mock(ProviderRequestLogger::class);
        $logger->shouldReceive('record')->once()->with($provider, 'catalogue_retrieval', 'test-service', $result, Mockery::type('int'), null);
        $importer = new CatalogueImportService();

        $sync = new ProviderCatalogueSyncService($adapter, $registry, $importer, $logger);

        $this->assertSame(1, $sync->sync($provider, $service));
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
