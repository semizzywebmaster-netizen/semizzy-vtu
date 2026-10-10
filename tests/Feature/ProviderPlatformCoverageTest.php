<?php

namespace Tests\\Feature;

use App\\Models\\ApiProvider;
use App\\Models\\ProviderServiceMapping;
use App\\Models\\Service;
use App\\Models\\ServiceCategory;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Inertia\\Testing\\AssertableInertia as Assert;
use Tests\\TestCase;

class ProviderPlatformCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_dashboard_counts_only_live_verified_enabled_unpaused_mappings(): void
    {
        $category = ServiceCategory::query()->create([
            'key' => 'digital-services',
            'name' => 'Digital Services',
            'enabled' => true,
        ]);
        $service = Service::query()->create([
            'category_id' => $category->id,
            'key' => 'airtime',
            'name' => 'Airtime',
            'enabled' => true,
            'metadata' => [],
        ]);
        $live = ApiProvider::query()->create([
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
        $draft = ApiProvider::query()->create([
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
        foreach ([[$live, 'AIRTIME-001'], [$draft, 'AIRTIME-002']] as [$provider, $externalId]) {
            ProviderServiceMapping::query()->create([
                'api_provider_id' => $provider->id,
                'service_id' => $service->id,
                'service_key' => $service->key,
                'provider_service_id' => $externalId,
                'capabilities' => ['airtime'],
                'enabled' => true,
            ]);
        }

        $admin = User::factory()->create(['role' => 'ADMIN', 'email_verified_at' => now()]);
        $this->actingAs($admin)->withoutMiddleware()
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
