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

    public function test_dependency_version_constraint_and_core_compatibility_are_enforced(): void
    {
        $service = app(AddonLifecycleService::class);

        $dependency = $service->register([
            'identifier' => 'base-addon',
            'name' => 'Base Addon',
            'version' => '2.3.0',
        ]);
        $service->install($dependency);
        $service->activate($dependency);

        $addon = $service->register([
            'identifier' => 'dependent-versioned-addon',
            'name' => 'Dependent Versioned Addon',
            'version' => '1.0.0',
            'compatibility' => '>=2.0.0',
            'dependencies' => [
                ['identifier' => 'base-addon', 'constraint' => '^2.0.0'],
            ],
        ]);

        $installed = $service->install($addon);
        $this->assertSame('installed', $installed->status);

        $incompatible = $service->register([
            'identifier' => 'incompatible-addon',
            'name' => 'Incompatible Addon',
            'version' => '1.0.0',
            'compatibility' => '>=3.0.0',
        ]);

        $this->expectException(ValidationException::class);
        $service->install($incompatible);
    }

    public function test_update_requires_a_newer_version_and_preserves_active_state_on_success(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'updatable-addon',
            'name' => 'Updatable Addon',
            'version' => '1.0.0',
            'migrations' => ['2026_10_02_000001_initial'],
        ]);
        $service->install($addon);
        $service->activate($addon);

        try {
            $service->update($addon, [
                'identifier' => 'updatable-addon',
                'name' => 'Updatable Addon',
                'version' => '1.0.0',
            ]);
            $this->fail('Expected same-version update to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }

        $updated = $service->update($addon, [
            'identifier' => 'updatable-addon',
            'name' => 'Updatable Addon',
            'version' => '1.1.0',
            'compatibility' => '>=2.0.0',
            'migrations' => ['2026_10_02_000002_upgrade'],
        ]);

        $this->assertSame('1.1.0', $updated->version);
        $this->assertSame('active', $updated->status);
        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_id' => $addon->id,
            'event' => 'updated',
            'to_status' => 'active',
        ]);
        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_id' => $addon->id,
            'event' => 'step_migration_' . sha1('2026_10_02_000002_upgrade'),
        ]);
    }

    public function test_duplicate_registration_is_rejected_instead_of_silently_updating(): void
    {
        $service = app(AddonLifecycleService::class);

        $service->register([
            'identifier' => 'duplicate-addon',
            'name' => 'Duplicate Addon',
            'version' => '1.0.0',
        ]);

        $this->expectException(ValidationException::class);

        $service->register([
            'identifier' => 'duplicate-addon',
            'name' => 'Duplicate Addon',
            'version' => '2.0.0',
        ]);
    }
}
