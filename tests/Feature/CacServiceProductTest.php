<?php

namespace Tests\Feature;

use App\Models\CacServiceProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacServiceProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_product_uses_integer_minor_units_and_can_be_created(): void
    {
        $product = CacServiceProduct::create([
            'identifier'=>'cac.business-name.standard',
            'name'=>'Business Name Registration',
            'service_type'=>'business_name_registration',
            'currency'=>'NGN',
            'provider_price_minor'=>250000,
            'selling_price_minor'=>300000,
            'enabled'=>false,
            'requirements'=>['identity'=>true],
        ]);

        $this->assertSame(250000, $product->fresh()->provider_price_minor);
        $this->assertSame(300000, $product->fresh()->selling_price_minor);
        $this->assertSame(['identity'=>true], $product->fresh()->requirements);
    }
}
