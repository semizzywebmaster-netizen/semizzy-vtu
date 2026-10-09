<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderService;
use App\Models\ProviderServiceImport;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderCatalogueDraftMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_provider_catalogue_row_maps_to_a_draft_without_enabling_routing(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'email_verified_at' => now(),
        ]);

        $provider = ApiProvider::query()->create([
            'identifier' => 'test-provider-map',
            'display_name' => 'Test Provider Map',
            'base_url' => 'https://provider.example.test',
            'auth_type' => 'api_token',
            'environment' => 'sandbox',
            'verification_status' => 'pending',
            'integration_status' => 'not_tested',
            'enabled' => false,
            'paused' => true,
            'service_categories' => ['data'],
            'capabilities' => [],
        ]);

        $providerService = ProviderService::query()->create([
            'api_provider_id' => $provider->id,
            'external_service_id' => 'EXT-DATA-1GB',
            'external_service_code' => 'DATA-1GB',
            'name' => 'Test Network 1 GB Data',
            'service_type' => 'data',
            'network' => 'TEST',
            'provider_price' => '100.0000',
            'currency' => 'NGN',
            'status' => 'imported',
            'metadata' => [],
            'raw_provider_data' => [],
            'last_synced_at' => now(),
        ]);

        ProviderServiceImport::query()->create([
            'api_provider_id' => $provider->id,
            'provider_service_id' => $providerService->id,
            'selection_scope' => 'product',
            'imported' => true,
            'approved' => true,
            'auto_sync_allowed' => false,
            'state' => 'imported',
            'last_imported_at' => now(),
        ]);

        $category = ServiceCategory::query()->create([
            'key' => 'test-data-category',
            'name' => 'Test Data Category',
            'enabled' => true,
            'sort_order' => 1,
        ]);
        $service = Service::query()->create([
            'category_id' => $category->id,
            'key' => 'test-data-service',
            'name' => 'Test Data Service',
            'enabled' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(
            '/admin/providers/' . $provider->id . '/provider-services/' . $providerService->id . '/map-to-platform',
            ['service_id' => $service->id, 'provider_service_id' => 'data'],
        );

        $response->assertOk()
            ->assertJsonPath('status', 'mapped_to_draft')
            ->assertJsonPath('product.publication_status', 'draft')
            ->assertJsonPath('route_enabled', false);

        $productId = (int) $response->json('product.id');
        $this->assertDatabaseHas('service_products', ['id' => $productId, 'enabled' => false, 'publication_status' => 'draft']);
        $this->assertDatabaseHas('provider_service_products', [
            'api_provider_id' => $provider->id,
            'service_product_id' => $productId,
            'provider_product_id' => 'EXT-DATA-1GB',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('provider_service_mappings', [
            'api_provider_id' => $provider->id,
            'service_id' => $service->id,
            'provider_service_id' => 'EXT-DATA-1GB',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('provider_product_mappings_v2', [
            'api_provider_id' => $provider->id,
            'provider_service_id' => $providerService->id,
            'catalogue_product_id' => $productId,
            'enabled' => false,
            'mapping_status' => 'pending',
        ]);

        $serviceMapping = ProviderServiceMapping::query()
            ->where('api_provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->firstOrFail();
        $serviceRouteResponse = $this->actingAs($admin)->patchJson(
            '/admin/providers/' . $provider->id . '/service-mappings/' . $serviceMapping->id,
            ['enabled' => true],
        );
        $serviceRouteResponse->assertStatus(422);
        $this->assertDatabaseHas('provider_service_mappings', [
            'id' => $serviceMapping->id,
            'enabled' => false,
        ]);

        $productMapping = DB::table('provider_product_mappings_v2')
            ->where('api_provider_id', $provider->id)
            ->where('provider_service_id', $providerService->id)
            ->first();
        $productRouteResponse = $this->actingAs($admin)->patchJson(
            '/admin/providers/' . $provider->id . '/mappings/' . $productMapping->id,
            ['enabled' => true],
        );
        $productRouteResponse->assertStatus(422);
        $this->assertDatabaseHas('provider_product_mappings_v2', [
            'id' => $productMapping->id,
            'enabled' => false,
            'mapping_status' => 'pending',
        ]);
    }
}
