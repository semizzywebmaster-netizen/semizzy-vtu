<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderServiceProduct;
use App\Models\PriceRule;
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

        PriceRule::create([
            'scope_type' => 'GLOBAL',
            'scope_id' => null,
            'customer_tier' => null,
            'rule_type' => 'percentage',
            'fixed_fee' => '0.000000',
            'percentage' => '10.000000',
            'enabled' => true,
            'priority' => 100,
        ]);

        $quote = app(PriceEngine::class)->quote($product);

        $this->assertSame($provider->id, $quote['provider_id']);
        $this->assertSame('123.450000', $quote['provider_cost']);
        $this->assertSame('135.80', $quote['customer_price']);
        $this->assertNotNull($quote['rule_id']);
    }

    public function test_quote_fails_closed_when_no_live_verified_provider_cost_exists(): void
    {
        [, , $product] = $this->makeProduct();
        $product->forceFill(['provider_cost' => '50.00'])->save();

        $this->expectException(InvalidArgumentException::class);
        app(PriceEngine::class)->quote($product);
    }

    public function test_quote_fails_closed_without_a_selling_price_rule(): void
    {
        [, $service, $product] = $this->makeProduct();
        $provider = $this->makeLiveProvider('price-no-rule-provider');
        ProviderServiceMapping::create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'enabled' => true,
        ]);
        ProviderServiceProduct::create([
            'api_provider_id' => $provider->id,
            'service_product_id' => $product->id,
            'provider_product_id' => 'provider-no-rule',
            'provider_cost' => '50.000000',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(PriceEngine::class)->quote($product);
    }

    public function test_invalid_pricing_evaluation_timestamp_is_rejected(): void
    {
        $product = ServiceProduct::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        app(\App\Services\Pricing\PriceEngine::class)->quote($product, 'USER', 'not-a-date');
    }

    public function test_price_rule_rejects_negative_amounts_and_invalid_effective_window(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PriceRule::create([
            'scope_type' => 'GLOBAL',
            'rule_type' => 'percentage',
            'percentage' => '-1.000000',
            'enabled' => true,
        ]);
    }

    public function test_price_rule_rejects_effective_window_with_start_after_end(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PriceRule::create([
            'scope_type' => 'GLOBAL',
            'rule_type' => 'fixed',
            'fixed_fee' => '10.000000',
            'effective_from' => now()->addDay(),
            'effective_to' => now(),
            'enabled' => true,
        ]);
    }

    public function test_provider_cost_currency_must_match_product_currency(): void
    {
        [, $service, $product] = $this->makeProduct();
        $provider = $this->makeLiveProvider('price-currency-provider');

        ProviderServiceMapping::create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'enabled' => true,
        ]);

        ProviderServiceProduct::create([
            'api_provider_id' => $provider->id,
            'service_product_id' => $product->id,
            'provider_product_id' => 'usd-bundle',
            'provider_cost' => '40.000000',
            'currency' => 'USD',
            'enabled' => true,
        ]);

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
