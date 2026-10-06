<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ProviderIdempotencyService;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderRequestLogger;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_transaction_key_replays_without_a_second_provider_call(): void
    {
        [$service, $provider] = $this->providerFor('idempotent-success');

        $adapter = $this->mock(RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->andReturn(new ProviderResult(
            true,
            'SUCCESS',
            providerReference: 'PROVIDER-1',
            message: 'Accepted',
        ));
        $this->mock(ProviderRequestLogger::class);

        $manager = app(ProviderManager::class);

        $first = $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08000000000'], 'idem-success');
        $second = $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08000000000'], 'idem-success');

        $this->assertTrue($first->accepted);
        $this->assertTrue($second->accepted);
        $this->assertSame('PROVIDER-1', $second->providerReference);
        $this->assertDatabaseCount('provider_idempotency_records', 1);
    }

    public function test_reusing_a_key_with_a_different_payload_is_rejected(): void
    {
        [$service, $provider] = $this->providerFor('idempotent-mismatch');

        $adapter = $this->mock(RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->andReturn(new ProviderResult(
            true,
            'SUCCESS',
            providerReference: 'PROVIDER-2',
        ));
        $this->mock(ProviderRequestLogger::class);

        $manager = app(ProviderManager::class);
        $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08000000000'], 'idem-mismatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Idempotency key was already used with a different request payload.');

        $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08111111111'], 'idem-mismatch');
    }

    public function test_unknown_provider_state_is_replayed_without_failover_or_second_attempt(): void
    {
        [$service, $provider] = $this->providerFor('idempotent-unknown');

        $adapter = $this->mock(RestJsonProviderAdapter::class);
        $adapter->shouldReceive('execute')->once()->andReturn(new ProviderResult(
            false,
            'UNKNOWN',
            providerReference: 'PROVIDER-UNKNOWN',
            message: 'Timeout after submission.',
            duplicateRisk: true,
        ));
        $this->mock(ProviderRequestLogger::class);

        $manager = app(ProviderManager::class);
        $first = $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08000000000'], 'idem-unknown');
        $second = $manager->executeProvider($provider, $service->key, 'transaction_initiation', ['recipient' => '08000000000'], 'idem-unknown');

        $this->assertSame('UNKNOWN', $first->status);
        $this->assertTrue($first->duplicateRisk);
        $this->assertSame('UNKNOWN', $second->status);
        $this->assertTrue($second->duplicateRisk);
        $this->assertSame('PROVIDER-UNKNOWN', $second->providerReference);
    }

    public function test_expired_in_progress_record_can_be_reclaimed(): void
    {
        [$service, $provider] = $this->providerFor('idempotent-reclaim');

        $idempotency = app(ProviderIdempotencyService::class);
        $first = $idempotency->reserve($provider, 'idem-reclaim', ['recipient' => '08000000000'], 60);
        $first['record']->forceFill(['locked_until' => now()->subMinute()])->save();

        $second = $idempotency->reserve($provider, 'idem-reclaim', ['recipient' => '08000000000'], 60);

        $this->assertFalse($second['replay']);
        $this->assertFalse($second['created']);
        $this->assertSame('IN_PROGRESS', $second['record']->state);
    }

    private function providerFor(string $suffix): array
    {
        $category = ServiceCategory::create([
            'key' => 'provider-idempotency-'.$suffix,
            'name' => 'Provider Idempotency Tests',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'provider-idempotency-'.$suffix,
            'name' => 'Provider Idempotency Service',
            'enabled' => true,
        ]);

        $provider = ApiProvider::create([
            'identifier' => 'provider-'.$suffix,
            'display_name' => 'Provider '.$suffix,
            'base_url' => 'https://example.com',
            'credentials' => ['token' => 'test-secret'],
            'capabilities' => ['transaction_initiation', 'transaction_status'],
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
            'priority' => 1,
            'timeout_seconds' => 10,
        ]);

        ProviderServiceMapping::create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'provider_service_id' => $service->key,
            'capabilities' => ['transaction_initiation', 'transaction_status'],
            'enabled' => true,
        ]);

        return [$service, $provider];
    }
}
