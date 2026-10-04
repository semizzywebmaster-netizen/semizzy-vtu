<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\Providers\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_are_ordered_by_core_provider_priority(): void
    {
        [$service] = $this->makeService('airtime');
        $slow = $this->makeProvider('slow', 20);
        $fast = $this->makeProvider('fast', 5);

        foreach ([$slow, $fast] as $provider) {
            ProviderServiceMapping::create([
                'api_provider_id' => $provider->id,
                'service_id' => $service->id,
                'service_key' => $service->key,
                'provider_service_id' => $service->key,
                'capabilities' => ['transaction_initiation', 'transaction_status'],
                'enabled' => true,
            ]);
        }

        $manager = app(ProviderManager::class);
        $this->assertSame([$fast->id, $slow->id], $manager->eligible('airtime')->pluck('id')->all());
    }

    public function test_mapping_capabilities_can_restrict_an_operation(): void
    {
        [$service] = $this->makeService('data');
        $provider = $this->makeProvider('data-provider', 1);

        ProviderServiceMapping::create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'provider_service_id' => 'data',
            'capabilities' => ['catalogue_retrieval'],
            'enabled' => true,
        ]);

        $routing = app(\App\Services\Providers\ProviderRoutingService::class);
        $this->assertCount(0, $routing->candidates('data', 'transaction_initiation'));
        $this->assertCount(1, $routing->candidates('data', 'catalogue_retrieval'));
    }

    public function test_transaction_unknown_or_duplicate_risk_never_fails_over_to_another_provider(): void
    {
        [$service] = $this->makeService('electricity');
        $first = $this->makeProvider('first', 1);
        $second = $this->makeProvider('second', 2);

        foreach ([$first, $second] as $provider) {
            ProviderServiceMapping::create([
                'api_provider_id' => $provider->id,
                'service_id' => $service->id,
                'service_key' => $service->key,
                'provider_service_id' => 'electricity',
                'capabilities' => ['transaction_initiation', 'transaction_status'],
                'enabled' => true,
            ]);
        }

        $adapter = Mockery::mock(\App\Services\Providers\RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->with($first, 'transaction_initiation', ['recipient' => '123'], 'idem-1')
            ->andReturn(new \App\Services\Providers\ProviderResult(false, 'UNKNOWN', duplicateRisk: true));
        $adapter->shouldReceive('execute')->never()->with($second, 'transaction_initiation', Mockery::any(), Mockery::any());

        $this->app->instance(\App\Services\Providers\RestJsonProviderAdapter::class, $adapter);

        $result = app(ProviderManager::class)->execute('electricity', 'transaction_initiation', ['recipient' => '123'], 'idem-1');

        $this->assertSame('UNKNOWN', $result->status);
        $this->assertTrue($result->duplicateRisk);
    }

    private function makeService(string $key): array
    {
        $category = ServiceCategory::create([
            'key' => 'vtu-'.$key,
            'name' => 'VTU',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        return [Service::create([
            'category_id' => $category->id,
            'key' => $key,
            'name' => ucfirst($key),
            'enabled' => true,
        ])];
    }

    private function makeProvider(string $identifier, int $priority): ApiProvider
    {
        return ApiProvider::create([
            'identifier' => $identifier,
            'display_name' => strtoupper($identifier),
            'base_url' => 'https://example.com',
            'credentials' => ['token' => 'test-secret'],
            'capabilities' => ['transaction_initiation', 'transaction_status', 'catalogue_retrieval'],
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
            'priority' => $priority,
            'timeout_seconds' => 10,
        ]);
    }
}
