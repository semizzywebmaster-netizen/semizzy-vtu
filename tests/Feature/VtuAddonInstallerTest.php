<?php

namespace Tests\Feature;

use App\Services\Vtu\VtuAddonInstaller;
use App\Services\Vtu\VtuServiceRegistry;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class VtuAddonInstallerTest extends TestCase
{
    public function test_installer_runs_only_vtu_migrations_before_bootstrapping_catalogue(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->ordered()
            ->with('migrate', [
                '--path' => 'database/migrations/2026_10_05_000026_create_vtu_addon_tables.php',
                '--force' => true,
            ])
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->ordered()
            ->with('migrate', [
                '--path' => 'database/migrations/2026_10_05_000027_add_vtu_bulk_idempotency.php',
                '--force' => true,
            ])
            ->andReturn(0);

        $registry = Mockery::mock(VtuServiceRegistry::class);
        $registry->shouldReceive('bootstrapCatalogue')->once();

        app(VtuAddonInstaller::class, ['registry' => $registry])->install();

        $this->assertTrue(true);
    }

    public function test_installer_stops_before_catalogue_bootstrap_when_a_vtu_migration_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', [
                '--path' => 'database/migrations/2026_10_05_000026_create_vtu_addon_tables.php',
                '--force' => true,
            ])
            ->andReturn(1);

        $registry = Mockery::mock(VtuServiceRegistry::class);
        $registry->shouldNotReceive('bootstrapCatalogue');

        $this->expectException(\RuntimeException::class);

        app(VtuAddonInstaller::class, ['registry' => $registry])->install();
    }
}
