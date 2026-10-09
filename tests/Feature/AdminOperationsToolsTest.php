<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Platform\FeatureRolloutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_global_search_endpoint_returns_bounded_result_contract(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/global-search?q=semizzy');

        $response->assertOk()
            ->assertJsonStructure(['query', 'results'])
            ->assertJsonPath('query', 'semizzy');
        $this->assertLessThanOrEqual(15, count($response->json('results')));
    }

    public function test_rollout_defaults_to_enabled_for_existing_addons_without_a_saved_flag(): void
    {
        $user = User::factory()->create(['role' => 'USER']);

        $this->assertTrue(app(FeatureRolloutService::class)->allows('example.addon', $user));
    }

    public function test_rollout_can_be_disabled_and_admin_keeps_recovery_access(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'feature_rollouts'],
            [
                'value' => json_encode(['example.addon' => ['enabled' => false, 'percentage' => 100]]),
                'type' => 'json',
                'is_secret' => false,
            ],
        );

        $user = User::factory()->create(['role' => 'USER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $service = app(FeatureRolloutService::class);
        $this->assertFalse($service->allows('example.addon', $user));
        $this->assertTrue($service->allows('example.addon', $admin));
    }

    public function test_zero_percent_excludes_users_and_full_rollout_includes_them(): void
    {
        $user = User::factory()->create(['role' => 'USER']);
        $service = app(FeatureRolloutService::class);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'feature_rollouts'],
            ['value' => json_encode(['zero' => ['enabled' => true, 'percentage' => 0], 'full' => ['enabled' => true, 'percentage' => 100]]), 'type' => 'json', 'is_secret' => false],
        );

        $this->assertFalse($service->allows('zero', $user));
        $this->assertTrue($service->allows('full', $user));
    }

    public function test_percentage_assignment_is_stable_for_the_same_user(): void
    {
        $user = User::factory()->create(['role' => 'USER']);
        SystemSetting::query()->updateOrCreate(
            ['key' => 'feature_rollouts'],
            ['value' => json_encode(['gradual' => ['enabled' => true, 'percentage' => 50]]), 'type' => 'json', 'is_secret' => false],
        );

        $service = app(FeatureRolloutService::class);
        $first = $service->allows('gradual', $user);
        $second = $service->allows('gradual', $user);

        $this->assertSame($first, $second);
    }
}
