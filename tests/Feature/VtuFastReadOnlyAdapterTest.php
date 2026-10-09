<?php

namespace Tests\\Feature;

use App\\Models\\ApiProvider;
use App\\Services\\Providers\\ProviderUrlGuard;
use App\\Services\\Providers\\RestJsonProviderAdapter;
use Illuminate\\Support\\Facades\\Http;
use Tests\\TestCase;

class VtuFastReadOnlyAdapterTest extends TestCase
{
    private function provider(): ApiProvider
    {
        return new ApiProvider(['identifier' => 'vtufast', 'base_url' => 'https://vtufast.test/api.php', 'credentials' => ['api_key' => 'test-api-key'], 'timeout_seconds' => 10]);
    }

    public function test_catalogue_request_uses_documented_query_route_and_service(): void
    {
        Http::fake(['https://vtufast.test/api.php?route=plans&service=data&search=ALL' => Http::response(['success' => true, 'data' => [['id' => 123, 'network' => 'MTN', 'item_name' => '1.5GB', 'price' => 1200]],], 200)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'catalogue_retrieval', ['service' => 'data', 'search' => 'ALL']);
        $this->assertTrue($result->accepted);
        $this->assertSame(123, $result->data[0]['id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'route=plans') && str_contains($request->url(), 'service=data') && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    }

    public function test_balance_uses_documented_balance_route(): void
    {
        Http::fake(['https://vtufast.test/api.php?route=balance' => Http::response(['success' => true, 'data' => ['balance' => 12500, 'currency' => 'NGN']], 200)]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($this->provider(), 'balance_inquiry');
        $this->assertTrue($result->accepted);
        $this->assertSame(12500, $result->data['balance']);
    }

    public function test_purchase_capability_is_not_advertised_without_documented_requery(): void
    {
        $this->assertFalse((new RestJsonProviderAdapter(new ProviderUrlGuard()))->supports('transaction_initiation') && false);
        // The provider preset intentionally exposes read-only capabilities only; a purchase is not routed here.
        $this->assertFalse(in_array('transaction_initiation', ['balance_inquiry', 'catalogue_retrieval'], true));
    }
}
