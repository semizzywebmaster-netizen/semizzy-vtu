<?php

namespace Tests\Feature;

use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Semizzy\Addons\SimHosting\Services\SimHostingProviderAdapter;
use Tests\TestCase;

class SimHostingProviderAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sim_hosting_business_operations_delegate_using_core_operation_names(): void
    {
        $manager = Mockery::mock(ProviderManager::class);
        $accepted = new ProviderResult(true, 'ACCEPTED');

        $manager->shouldReceive('execute')->once()->with('sim-hosting', 'transaction_initiation', ['msisdn' => '08000000000'], 'airtime-idem')->andReturn($accepted);
        $manager->shouldReceive('execute')->once()->with('sim-hosting', 'transaction_initiation', ['plan' => 'data-plan'], 'data-idem')->andReturn($accepted);
        $manager->shouldReceive('execute')->once()->with('sim-hosting', 'catalogue_retrieval', ['network' => 'test-network'], null)->andReturn($accepted);
        $manager->shouldReceive('execute')->once()->with('sim-hosting', 'sms_send', ['to' => '08000000000', 'message' => 'test'], 'sms-idem')->andReturn($accepted);
        $manager->shouldReceive('execute')->once()->with('sim-hosting', 'balance_inquiry', ['account' => 'provider'], null)->andReturn($accepted);
        $manager->shouldReceive('execute')->twice()->with('sim-hosting', 'transaction_status', ['reference' => 'provider-ref'], null)->andReturn($accepted);

        $adapter = new SimHostingProviderAdapter($manager);

        $this->assertSame($accepted, $adapter->airtime(['msisdn' => '08000000000'], 'airtime-idem'));
        $this->assertSame($accepted, $adapter->data(['plan' => 'data-plan'], 'data-idem'));
        $this->assertSame($accepted, $adapter->dataCatalogue(['network' => 'test-network']));
        $this->assertSame($accepted, $adapter->sms(['to' => '08000000000', 'message' => 'test'], 'sms-idem'));
        $this->assertSame($accepted, $adapter->balance(['account' => 'provider']));
        $this->assertSame($accepted, $adapter->status(['reference' => 'provider-ref']));
        $this->assertSame($accepted, $adapter->requery(['reference' => 'provider-ref']));
    }

    public function test_sim_hosting_rejects_empty_purchase_and_sms_payloads_before_core_call(): void
    {
        $manager = Mockery::mock(ProviderManager::class);
        $manager->shouldNotReceive('execute');
        $adapter = new SimHostingProviderAdapter($manager);

        $this->expectException(\RuntimeException::class);
        $adapter->airtime([]);
    }

    public function test_core_rest_adapter_accepts_provider_number_reservation_operations(): void
    {
        $adapter = app(RestJsonProviderAdapter::class);

        $this->assertTrue($adapter->supports('number_reservation'));
        $this->assertTrue($adapter->supports('number_release'));
    }
}
