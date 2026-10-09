<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderService;
use App\Models\ProviderServiceImport;
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

    public function test_product_publishes_only_when_live_provider_route_and_all_tier_prices_are_ready(): void
    {
        $category = ServiceCategory::query()->create(['key' => 'ready-category', 'name' => 'Ready Category', 'enabled' => true]);
        $service = Service::query()->create(['category_id' => $category->id, 'key' => 'ready-service', 'name' => 'Ready Service', 'enabled' => true, 'metadata' => []]);
        $product = ServiceProduct::query()->create(['service_id' => $service->id, 'key' => 'ready-product', 'name' => 'Ready Product', 'currency' => 'NGN', 'enabled' => false, 'publication_status' => 'draft']);
        $provider = ApiProvider::query()->create([
            'identifier' => 'ready-provider',
            'display_name' => 'Ready Provider',
            'environment' => 'production',
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
            'capabilities' => ['transaction_initiation'],
            'service_categories' => ['ready-service'],
        ]);
        ProviderServiceMapping::query()->create([
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'service_key' => $service->key,
            'provider_service_id' => 'READY-001',
            'capabilities' => ['transaction_initiation'],
            'enabled' => true,
        ]);
        $product->providerProducts()->create([
            'api_provider_id' => $provider->id,
            'provider_product_id' => 'READY-EXT-001',
            'provider_cost' => '100.000000',
            'currency' => 'NGN',
            'raw_catalogue' => ['source' => 'test-fixture'],
            'enabled' => true,
            'last_synced_at' => now(),
        ]);

        foreach (['USER', 'AGENT', 'RESELLER', 'MERCHANT'] as $tier) {
            \App\Models\PriceRule::query()->create([
                'scope_type' => 'GLOBAL',
                'scope_id' => null,
                'customer_tier' => $tier,
                'rule_type' => 'fixed',
                'fixed_fee' => '10.000000',
                'percentage' => '0',
                'enabled' => true,
                'priority' => 1,
            ]);
        }

        $admin = \App\Models\User::factory()->create(['role' => 'ADMIN']);
        $result = app(ProductPublicationService::class)->publish($product, $admin->id);

        $this->assertTrue($result['published']);
        $this->assertSame([], $result['blockers']);
        $this->assertTrue($result['product']->enabled);
        $this->assertSame('published', $result['product']->publication_status);
        $this->assertSame((int) $admin->id, (int) $result['product']->published_by);
        $this->assertNotNull($result['product']->published_at);
        $this->assertDatabaseHas('audit_events', ['event' => 'provider_platform.product_published', 'auditable_id' => $product->id]);
    }

    public function test_provider_catalogue_selection_is_pending_and_does_not_publish_or_enable_routing(): void
    {
        $provider = ApiProvider::query()->create([
            'identifier' => 'catalogue-review-provider',
            'display_name' => 'Catalogue Review Provider',
            'environment' => 'sandbox',
            'verification_status' => 'unverified',
            'integration_status' => 'draft',
            'enabled' => false,
            'paused' => true,
            'capabilities' => [],
            'service_categories' => [],
        ]);
        $providerService = ProviderService::query()->create([
            'api_provider_id' => $provider->id,
            'external_service_id' => 'DISCOVERED-001',
            'external_service_code' => 'DISC-001',
            'name' => 'Discovered Catalogue Entry',
            'provider_price' => '125.0000',
            'currency' => 'NGN',
            'status' => 'discovered',
            'metadata' => ['source' => 'test-fixture'],
            'raw_provider_data' => ['secret_like_field' => 'must-not-be-returned'],
            'last_synced_at' => now(),
        ]);

        $this->withoutMiddleware()
            ->getJson('/admin/provider-platform/catalogue?provider_id=' . $provider->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.external_service_id', 'DISCOVERED-001')
            ->assertJsonPath('data.0.source_price', '125.0000')
            ->assertJsonMissingPath('data.0.raw_provider_data');

        $this->withoutMiddleware()
            ->postJson('/admin/provider-platform/catalogue/' . $providerService->id . '/select', [
                'selection_scope' => 'product',
            ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'awaiting_approval')
            ->assertJsonPath('selection.approved', false)
            ->assertJsonPath('selection.imported', false)
            ->assertJsonPath('selection.auto_sync_allowed', false);

        $this->assertDatabaseHas('provider_service_imports', [
            'api_provider_id' => $provider->id,
            'provider_service_id' => $providerService->id,
            'state' => 'awaiting_approval',
            'approved' => false,
            'imported' => false,
            'auto_sync_allowed' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event' => 'provider_platform.catalogue_service_selected',
            'auditable_id' => $providerService->id,
        ]);

        $selection = ProviderServiceImport::query()->where('provider_service_id', $providerService->id)->firstOrFail();
        $selection->forceFill([
            'state' => 'imported',
            'approved' => true,
            'imported' => true,
            'auto_sync_allowed' => true,
        ])->save();

        $this->withoutMiddleware()
            ->postJson('/admin/provider-platform/catalogue/' . $providerService->id . '/select', [
                'selection_scope' => 'product',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'imported')
            ->assertJsonPath('selection.approved', true)
            ->assertJsonPath('selection.imported', true)
            ->assertJsonPath('selection.auto_sync_allowed', true);

        $this->assertDatabaseHas('provider_service_imports', [
            'id' => $selection->id,
            'state' => 'imported',
            'approved' => true,
            'imported' => true,
            'auto_sync_allowed' => true,
        ]);
    }


    public function test_provider_catalogue_review_page_shows_only_safe_source_fields_and_review_actions(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'ADMIN']);
        $provider = ApiProvider::query()->create([
            'identifier' => 'catalogue-page-provider',
            'display_name' => 'Catalogue Page Provider',
            'environment' => 'sandbox',
            'verification_status' => 'unverified',
            'integration_status' => 'draft',
            'enabled' => false,
            'paused' => true,
            'capabilities' => [],
            'service_categories' => [],
        ]);
        ProviderService::query()->create([
            'api_provider_id' => $provider->id,
            'external_service_id' => 'PAGE-DISC-001',
            'external_service_code' => 'PAGE-001',
            'name' => 'Page Catalogue Entry',
            'provider_price' => '49.5000',
            'currency' => 'NGN',
            'status' => 'discovered',
            'metadata' => ['source' => 'test-fixture'],
            'raw_provider_data' => ['secret_like_field' => 'must-not-be-returned'],
            'last_synced_at' => now(),
        ]);

        $this->actingAs($admin)->withoutMiddleware()
            ->get('/admin/provider-platform/catalogue/review?status=awaiting_approval')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ProviderPlatformCatalogue')
                ->where('meta.total', 0)
                ->where('canManage', true)
                ->where('filters.status', 'awaiting_approval')
                ->has('providers', 1)
            );

        $this->withoutMiddleware()
            ->getJson('/admin/provider-platform/catalogue?status=awaiting_approval')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

}
