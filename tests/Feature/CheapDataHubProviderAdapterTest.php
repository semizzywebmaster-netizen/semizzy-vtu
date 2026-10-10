<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderUrlGuard;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheapDataHubProviderAdapterTest extends TestCase
{
    private function provider(): ApiProvider
    {
        return new ApiProvider(['identifier' => 'cheapdatahub', 'base_url' => 'https://example.com/api/v1/resellers', 'credentials' => ['api_key' => 'test-api-key'], 'timeout_seconds' => 10]);
    }

    public function test_airtime_purchase_uses_documented_provider_id_and_phone_fields(): void
    {
        Http::fake(['https://example.com/api/v1/resellers/airtime/purchase/' => Http::response(['status' => 'true', 'transaction_id' => 449, 'message' => 'Airtime purchase successful'], 200)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['provider_id' => 1, 'phone_number' => '08012345678', 'amount' => 100], 'semizzy-airtime-00001');
        $this->assertTrue($result->accepted);
        $this->assertSame('449', $result->providerReference);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/airtime/purchase/') && ($request->data()['provider_id'] ?? null) === 1 && ($request->data()['phone_number'] ?? null) === '08012345678' && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    }

    public function test_data_purchase_requires_provider_bundle_id_and_does_not_guess_it_from_network(): void
    {
        Http::fake();
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['network' => 'mtn', 'phone' => '08012345678'], 'semizzy-data-00001');
        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        Http::assertNothingSent();
    }

    public function test_status_lookup_requires_the_provider_transaction_id(): void
    {
        Http::fake();
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_status', ['reference' => 'local-reference']);
        $this->assertSame('FAILED', $result->status);
        Http::assertNothingSent();
    }

    public function test_network_failure_after_purchase_is_unknown_and_blocks_automatic_failover(): void
    {
        Http::fake(['https://example.com/api/v1/resellers/airtime/purchase/' => Http::response(['message' => 'gateway timeout'], 503)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'transaction_initiation', ['provider_id' => 1, 'phone_number' => '08012345678', 'amount' => 100], 'semizzy-airtime-00002');
        $this->assertFalse($result->accepted);
        $this->assertSame('UNKNOWN', $result->status);
        $this->assertTrue($result->duplicateRisk);
    }
}
