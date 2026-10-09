<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use Semizzy\Addons\ApiProviderPlatform\Services\ProductPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderPlatformCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_publication_stays_draft_when_live_provider_and_tier_prices_are_missing(): void
    {
        $category = ServiceCategory::query()->create([
            'key' => 'publication-category',
            'name' => 'Publication Category',
            'enabled' => true,
        ]);
        $service = Service::query()->create([
            'category_id' => $category->id,
            'key' => 'publication-service',
            'name' => 'Publication Service',
            'enabled' => true,
            'metadata' => [],
        ]);
        $product = ServiceProduct::query()->create([
            'service_id' => $service->id,
            'key' => 'publication-product',
            'name' => 'Publication Product',
            'currency' => 'NGN',
            'enabled' => false,
            'publication_status' => 'draft',
        ]);

        $result = app(ProductPublicationService::class)->publish($product);

        $this->assertFalse($result['published']);
        $this->assertNotEmpty($result['blockers']);
        $this->assertFalse($result['product']->enabled);
        $this->assertSame('draft', $result['product']->publication_status);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'provider_platform.product_publication_blocked',
            'auditable_id' => $product->id,
        ]);
    }

    public function test_coverage_dashboard_counts_only_live_verified_enabled_unpaused_mappings(): void
    {
        $category = ServiceCategory::query()->create([
            'key' => 'digital-services',
            'name' => 'Digital Services',
            'description' => 'Test category',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::query()->create([
            'category_id' => $category->id,
            'key' => 'airtime',
            'name' => 'Airtime',
            'description' => 'Airtime top-up',
            'enabled' => true,
            'metadata' => [],
        ]);

        $liveProvider = ApiProvider::query()->create([
            'identifier' => 'verified-airtime-provider',
            'display_name' => 'Verified Airtime Provider',
            'environment' => 'production',
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
            'capabilities' => ['airtime'],
            'service_categories' => ['airtime'],
        ]);

        $draftProvider = ApiProvider::query()->create([
            'identifier' => 'draft-airtime-provider',
            'display_name' => 'Draft Airtime Provider',
            'environment' => 'sandbox',
            'verification_status' => 'unverified',
            'integration_status' => 'draft',
            'enabled' => false,
            'paused' => true,
            'capabilities' => ['airtime'],
            'service_categories' => ['airtime'],
        ]);

        ProviderServiceMapping::query()->create([
            'api_provider_id' => $liveProvider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'provider_service_id' => 'AIRTIME-001',
            'capabilities' => ['airtime'],
            'enabled' => true,
        ]);

        ProviderServiceMapping::query()->create([
            'api_provider_id' => $draftProvider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'provider_service_id' => 'AIRTIME-002',
            'capabilities' => ['airtime'],
            'enabled' => true,
        ]);

        $this->withoutMiddleware()
            ->get('/admin/provider-platform')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ProviderPlatformCoverage')
                ->where('summary.total_providers', 2)
                ->where('summary.live_verified_providers', 1)
                ->where('summary.production_eligible_providers', 1)
                ->where('summary.enabled_services_with_live_coverage', 1)
                ->where('services.0.live_verified_provider_count', 1)
                ->where('services.0.mapped_provider_count', 2)
            );
    }
}
