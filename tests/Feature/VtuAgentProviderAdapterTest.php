<?php

namespace Tests\\Feature;

use App\\Models\\ApiProvider;
use App\\Services\\Providers\\ProviderUrlGuard;
use App\\Services\\Providers\\RestJsonProviderAdapter;
use Illuminate\\Support\\Facades\\Http;
use Tests\\TestCase;

class VtuAgentProviderAdapterTest extends TestCase
{
    private function provider(): ApiProvider
    {
        return new ApiProvider(['identifier' => 'vtuagent', 'base_url' => 'https://vtuagent.test/v1', 'credentials' => ['api_key' => 'test-api-key'], 'timeout_seconds' => 10]);
    }

    public function test_airtime_purchase_sends_documented_payload_and_unique_request_reference(): void
    {
        Http::fake(['https://vtuagent.test/v1/airtime/purchase' => Http::response(['status' => 'successful', 'reference' => 'semizzy-vtu-000001'], 200)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['network' => 'mtn', 'phone' => '08012345678', 'amount' => 100, 'request_ref' => 'semizzy-vtu-000001']);
        $this->assertTrue($result->accepted);
        $this->assertSame('ACCEPTED', $result->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/airtime/purchase') && ($request->data()['request_ref'] ?? null) === 'semizzy-vtu-000001' && ($request->data()['network'] ?? null) === 'mtn' && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    }

    public function test_data_purchase_uses_plan_id_and_pending_response_is_not_success(): void
    {
        Http::fake(['https://vtuagent.test/v1/data/purchase' => Http::response(['status' => 'pending', 'reference' => 'semizzy-data-000001'], 202)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['plan_id' => 'mtn_1gb_30d', 'phone' => '08012345678', 'request_ref' => 'semizzy-data-000001']);
        $this->assertFalse($result->accepted);
        $this->assertSame('PENDING', $result->status);
        $this->assertTrue($result->duplicateRisk === false);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/data/purchase') && ($request->data()['plan_id'] ?? null) === 'mtn_1gb_30d');
    }

    public function test_status_lookup_requeries_by_original_request_reference(): void
    {
        Http::fake(['https://vtuagent.test/v1/transaction/status' => Http::response(['status' => 'successful', 'reference' => 'semizzy-data-000001'], 200)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_status', ['request_ref' => 'semizzy-data-000001']);
        $this->assertTrue($result->accepted);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/status') && ($request->data()['request_ref'] ?? null) === 'semizzy-data-000001');
    }

    public function test_airtime_requires_reference_before_any_network_request(): void
    {
        Http::fake();
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['network' => 'mtn', 'phone' => '08012345678', 'amount' => 100]);
        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        Http::assertNothingSent();
    }
}
