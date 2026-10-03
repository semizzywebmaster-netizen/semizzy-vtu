<?php

namespace Tests\Unit;

use App\Models\ServiceProduct;
use App\Services\Pricing\PriceEngine;
use Tests\TestCase;

class PricingCustomerTierTest extends TestCase
{
    public function test_customer_tier_is_normalized_before_pricing_rule_lookup(): void
    {
        $source = file_get_contents(base_path('app/Services/Pricing/PriceEngine.php'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString(
            '$customerTier = strtoupper($customerTier);',
            $source
        );
        $this->assertStringContainsString(
            '$rule = $this->rules($product, $customerTier, $at)->first();',
            $source
        );
    }
}
