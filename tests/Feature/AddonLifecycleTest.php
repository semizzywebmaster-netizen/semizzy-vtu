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

    public function test_installed_addon_can_be_uninstalled_and_archived_with_lifecycle_events(): void
    {
        $service = app(AddonLifecycleService::class);

        $addon = $service->register([
            'identifier' => 'uninstallable-addon',
            'name' => 'Uninstallable Addon',
            'version' => '1.0.0',
        ]);

        $service->install($addon);
        $uninstalled = $service->uninstall($addon);

        $this->assertSame('archived', $uninstalled->status);
        $this->assertNull($uninstalled->activated_at);
        $this->assertNull($uninstalled->last_error);

        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_id' => $addon->id,
            'event' => 'uninstall_started',
            'from_status' => 'installed',
            'to_status' => 'uninstalling',
        ]);
        $this->assertDatabaseHas('addon_lifecycle_events', [
            'addon_id' => $addon->id,
            'event' => 'uninstalled',
            'from_status' => 'uninstalling',
            'to_status' => 'archived',
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

    public function test_manifest_rejects_self_dependency(): void
    {
        $service = app(AddonLifecycleService::class);

        $this->expectException(ValidationException::class);
        $service->register([
            'identifier' => 'demo-addon',
            'name' => 'Demo Addon',
            'version' => '1.0.0',
            'dependencies' => ['demo-addon'],
        ]);
    }

    public function test_manifest_rejects_duplicate_dependencies(): void
    {
        $service = app(AddonLifecycleService::class);

        $this->expectException(ValidationException::class);
        $service->register([
            'identifier' => 'demo-addon',
            'name' => 'Demo Addon',
            'version' => '1.0.0',
            'dependencies' => ['other-addon', 'other-addon'],
        ]);
    }

    public function test_manifest_rejects_invalid_checksum(): void
    {
        $service = app(AddonLifecycleService::class);

        $this->expectException(ValidationException::class);
        $service->register([
            'identifier' => 'checksum-addon',
            'name' => 'Checksum Addon',
            'version' => '1.0.0',
            'checksum' => 'not-a-sha256',
        ]);
    }
    public function test_uninstall_blocks_scalar_and_object_dependents(): void
    {
        $service = app(AddonLifecycleService::class);

        $base = $service->register([
            'identifier' => 'base-uninstall-guard',
            'name' => 'Base Uninstall Guard',
            'version' => '1.0.0',
        ]);
        $service->install($base);
        $service->activate($base);

        foreach ([
            ['identifier' => 'scalar-dependent', 'dependencies' => ['base-uninstall-guard']],
            ['identifier' => 'object-dependent', 'dependencies' => [['identifier' => 'base-uninstall-guard']]],
        ] as $manifest) {
            $dependent = $service->register([
                'identifier' => $manifest['identifier'],
                'name' => $manifest['identifier'],
                'version' => '1.0.0',
                'dependencies' => $manifest['dependencies'],
            ]);
            $service->install($dependent);
            $service->activate($dependent);
        }

        $this->expectException(ValidationException::class);
        $service->uninstall($base);
    }

    public function test_update_rejects_dependency_cycle_back_to_the_updating_addon(): void
    {
        $service = app(AddonLifecycleService::class);

        $dependency = $service->register([
            'identifier' => 'cycle-dependency',
            'name' => 'Cycle Dependency',
            'version' => '1.0.0',
        ]);
        $service->install($dependency);
        $service->activate($dependency);

        $addon = $service->register([
            'identifier' => 'cycle-root',
            'name' => 'Cycle Root',
            'version' => '1.0.0',
            'dependencies' => ['cycle-dependency'],
        ]);
        $service->install($addon);
        $service->activate($addon);

        $this->expectException(ValidationException::class);

        $service->update($dependency, [
            'identifier' => 'cycle-dependency',
            'name' => 'Cycle Dependency',
            'version' => '1.1.0',
            'dependencies' => [['identifier' => 'cycle-root']],
        ]);
    }

}
