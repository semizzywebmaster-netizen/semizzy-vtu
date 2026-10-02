<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderServiceProduct;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Services\Pricing\PriceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PriceEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_uses_an_eligible_provider_cost_and_returns_the_provider_id(): void
    {
        [$category, $service, $product] = $this->makeProduct();
        $provider = $this->makeLiveProvider('price-live-provider');
        ProviderServiceMapping::create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'enabled' => true,
        ]);
        ProviderServiceProduct::create([
            'api_provider_id' => $provider->id,
            'service_product_id' => $product->id,
            'provider_product_id' => 'provider-bundle-1',
            'provider_cost' => '123.450000',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $quote = app(PriceEngine::class)->quote($product);

        $this->assertSame($provider->id, $quote['provider_id']);
        $this->assertSame('123.450000', $quote['provider_cost']);
        $this->assertSame('123.45', $quote['customer_price']);
    }

    public function test_quote_fails_closed_when_no_live_verified_provider_cost_exists(): void
    {
        [, , $product] = $this->makeProduct();
        $product->forceFill(['provider_cost' => '50.00'])->save();

        $this->expectException(InvalidArgumentException::class);
        app(PriceEngine::class)->quote($product);
    }

    public function test_explicit_provider_cannot_fall_back_to_another_provider_cost(): void
    {
        [, $service, $product] = $this->makeProduct();
        $eligible = $this->makeLiveProvider('price-eligible-provider');
        $unavailable = $this->makeLiveProvider('price-unavailable-provider', ['paused' => true]);

        foreach ([$eligible, $unavailable] as $provider) {
            ProviderServiceMapping::create([
                'api_provider_id' => $provider->id,
                'service_id' => $service->id,
                'service_key' => $service->key,
                'enabled' => true,
            ]);
            ProviderServiceProduct::create([
                'api_provider_id' => $provider->id,
                'service_product_id' => $product->id,
                'provider_product_id' => 'bundle-'.$provider->id,
                'provider_cost' => '40.000000',
                'currency' => 'NGN',
                'enabled' => true,
            ]);
        }

        $this->expectException(InvalidArgumentException::class);
        app(PriceEngine::class)->quote($product, 'USER', null, $unavailable);
    }

    public function test_disabled_product_cannot_be_quoted(): void
    {
        [, , $product] = $this->makeProduct(['enabled' => false]);

        $this->expectException(InvalidArgumentException::class);
        app(PriceEngine::class)->quote($product);
    }

    private function makeProduct(array $overrides = []): array
    {
        $category = ServiceCategory::create(['key' => 'price-category', 'name' => 'Price category', 'enabled' => true]);
        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'price-service',
            'name' => 'Price service',
            'enabled' => true,
        ]);
        $product = ServiceProduct::create(array_merge([
            'service_id' => $service->id,
            'key' => 'price-product',
            'name' => 'Price product',
            'currency' => 'NGN',
            'enabled' => true,
        ], $overrides));

        return [$category, $service, $product];
    }

    private function makeLiveProvider(string $identifier, array $overrides = []): ApiProvider
    {
        return ApiProvider::create(array_merge([
            'identifier' => $identifier,
            'display_name' => $identifier,
            'enabled' => true,
            'paused' => false,
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'capabilities' => ['transaction_initiation'],
        ], $overrides));
    }
}
