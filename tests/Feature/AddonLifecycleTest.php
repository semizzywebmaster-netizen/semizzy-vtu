<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AddonLifecycleEvent;
use App\Services\Addons\AddonLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AddonLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_can_be_registered_and_installed_as_inactive(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'test-addon',
            'name' => 'Test Addon',
            'version' => '1.0.0',
            'dependencies' => [],
            'permissions' => ['test.read'],
        ]);

        $installed = $service->install($addon);

        $this->assertSame('installed', $installed->status);
        $this->assertNotNull($installed->installed_at);
        $this->assertCount(1, AddonLifecycleEvent::query()->where('addon_id', $addon->id)->where('event', 'installed')->get());
    }

    public function test_missing_active_dependency_blocks_install_and_persists_failure_diagnostics(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'dependent-addon',
            'name' => 'Dependent Addon',
            'version' => '1.0.0',
            'dependencies' => ['missing-core'],
        ]);

        try {
            $service->install($addon);
            $this->fail('Expected dependency validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dependencies', $exception->errors());
        }

        $failed = $addon->fresh();

        $this->assertSame('failed', $failed->status);
        $this->assertNotEmpty($failed->last_error);
        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_id' => $addon->id,
            'event' => 'install_failed',
            'to_status' => 'failed',
        ]);
    }
}
