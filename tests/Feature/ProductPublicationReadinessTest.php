<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\Catalogue\ProductPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPublicationReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_eligible_provider_mapping_cannot_be_published(): void
    {
        $product = $this->makeDraftProduct();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $result = app(ProductPublicationService::class)->publish($product, $admin);

        $this->assertFalse($result['published']);
        $this->assertNotEmpty($result['assessment']['blockers']);
        $this->assertFalse($product->fresh()->enabled);
        $this->assertSame('draft', $product->fresh()->publication_status);
        $this->assertNotEmpty($product->fresh()->publication_blockers);
    }

    public function test_readiness_explains_disabled_service_and_missing_provider_routing(): void
    {
        $product = $this->makeDraftProduct(serviceEnabled: false);
        $result = app(ProductPublicationService::class)->assess($product);

        $this->assertFalse($result['ready']);
        $this->assertStringContainsString('Enable the platform service', implode(' ', $result['blockers']));
        $this->assertStringContainsString('No enabled provider product', implode(' ', $result['blockers']));
    }

    private function makeDraftProduct(bool $serviceEnabled = true): ServiceProduct
    {
        $category = ServiceCategory::query()->create([
            'key' => 'test-category',
            'name' => 'Test Category',
            'enabled' => true,
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'category_id' => $category->id,
            'key' => 'test-service',
            'name' => 'Test Service',
            'enabled' => $serviceEnabled,
        ]);

        return ServiceProduct::query()->create([
            'service_id' => $service->id,
            'key' => 'test-product',
            'name' => 'Test Product',
            'currency' => 'NGN',
            'enabled' => false,
            'publication_status' => 'draft',
        ]);
    }
}
