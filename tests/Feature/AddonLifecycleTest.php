<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AddonLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_can_be_registered_and_installed_without_activation(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'vtu.core',
            'name' => 'VTU Core Addon',
            'version' => '1.0.0',
            'dependencies' => [],
            'permissions' => ['services.view'],
            'navigation' => [],
            'settings' => [],
        ]);

        $this->assertSame('draft', $addon->status);

        $installed = $service->install($addon);

        $this->assertSame('installed', $installed->status);
        $this->assertNotNull($installed->installed_at);
        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_identifier' => 'vtu.core',
            'event' => 'installed',
            'to_status' => 'installed',
        ]);
    }

    public function test_missing_active_dependency_blocks_installation(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'dependent.addon',
            'name' => 'Dependent Addon',
            'version' => '1.0.0',
            'dependencies' => [['identifier' => 'missing.addon']],
        ]);

        $this->expectException(ValidationException::class);

        $service->install($addon);
    }
}
