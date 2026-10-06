<?php

namespace App\Services\Vtu;

use App\Models\Addon;
use Illuminate\Support\Facades\Artisan;

class VtuAddonInstaller
{
    public function __construct(private ?VtuServiceRegistry $registry = null)
    {
    }

    /**
     * Backward-compatible installer entry point.
     *
     * The generic addon lifecycle already applies manifest migrations before
     * invoking this hook. When called directly without an Addon instance,
     * retain the legacy installer contract used by standalone installation
     * tooling/tests and apply only the VTU-declared migrations.
     */
    public function install(?Addon $addon = null): void
    {
        if ($addon === null) {
            foreach ([
                '2026_10_05_000026_create_vtu_addon_tables.php',
                '2026_10_05_000027_add_vtu_bulk_idempotency.php',
            ] as $migration) {
                $exit = Artisan::call('migrate', [
                    '--path' => 'database/migrations/'.$migration,
                    '--force' => true,
                ]);

                if ($exit !== 0) {
                    throw new \RuntimeException("VTU addon migration failed: {$migration}");
                }
            }
        }

        ($this->registry ?? app(VtuServiceRegistry::class))->bootstrapCatalogue();
    }
}
