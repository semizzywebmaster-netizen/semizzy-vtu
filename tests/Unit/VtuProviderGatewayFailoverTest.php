<?php

namespace Tests\Unit;

use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\VtuTransaction;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use App\Services\Vtu\VtuProviderGateway;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class VtuProviderGatewayFailoverTest extends TestCase
{
    public function test_initiation_does_not_fail_over_after_unknown_or_pending_provider_state(): void
    {
        $first = new ApiProvider();
        $first->id = 1;
        $second = new ApiProvider();
        $second->id = 2;

        $service = new Service();
        $service->key = 'airtime';

        $tx = new VtuTransaction();
        $tx->api_provider_id = $first->id;
        $tx->idempotency_key = 'idem-unknown';
        $tx->setRelation('service', $service);

        $manager = Mockery::mock(ProviderManager::class);
        $manager->shouldReceive('eligible')
            ->once()
            ->with('airtime', 'transaction_initiation')
            ->andReturn(new Collection([$first, $second]));
        $manager->shouldReceive('executeProvider')
            ->once()
            ->with($first, 'airtime', 'transaction_initiation', ['phone' => '08000000000'], 'idem-unknown')
            ->andReturn(new ProviderResult(
                accepted: false,
                status: 'UNKNOWN',
                message: 'Provider timeout.',
                duplicateRisk: true,
                providerId: $first->id,
            ));
        $manager->shouldNotReceive('executeProvider')->with(
            $second,
            'airtime',
            'transaction_initiation',
            Mockery::any(),
            'idem-unknown'
        );

        $result = (new VtuProviderGateway($manager))->initiate($tx, ['phone' => '08000000000']);

        $this->assertSame('UNKNOWN', $result->status);
        $this->assertTrue($result->duplicateRisk);
    }

    public function test_initiation_fails_over_only_after_definitive_provider_failure(): void
    {
        $first = new ApiProvider();
        $first->id = 1;
        $second = new ApiProvider();
        $second->id = 2;

        $service = new Service();
        $service->key = 'airtime';

        $tx = new VtuTransaction();
        $tx->api_provider_id = $first->id;
        $tx->idempotency_key = 'idem-failover';
        $tx->setRelation('service', $service);

        $manager = Mockery::mock(ProviderManager::class);
        $manager->shouldReceive('eligible')
            ->once()
            ->with('airtime', 'transaction_initiation')
            ->andReturn(new Collection([$first, $second]));
        $manager->shouldReceive('executeProvider')
            ->once()
            ->with($first, 'airtime', 'transaction_initiation', ['phone' => '08000000000'], 'idem-failover')
            ->andReturn(new ProviderResult(
                accepted: false,
                status: 'FAILED',
                message: 'Provider rejected the request.',
                retryable: true,
                providerId: $first->id,
            ));
        $manager->shouldReceive('executeProvider')
            ->once()
            ->with($second, 'airtime', 'transaction_initiation', ['phone' => '08000000000'], 'idem-failover')
            ->andReturn(new ProviderResult(
                accepted: true,
                status: 'ACCEPTED',
                providerReference: 'PROVIDER-2',
                providerId: $second->id,
            ));

        $result = (new VtuProviderGateway($manager))->initiate($tx, ['phone' => '08000000000']);

        $this->assertTrue($result->accepted);
        $this->assertSame($second->id, $result->providerId);
        $this->assertSame('PROVIDER-2', $result->providerReference);
    }
}
