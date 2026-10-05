<?php

namespace App\Services\Vtu;

use Illuminate\Support\Facades\Artisan;

class VtuAddonInstaller
{
    private const MIGRATIONS = [
        'database/migrations/2026_10_05_000026_create_vtu_addon_tables.php',
        'database/migrations/2026_10_05_000027_add_vtu_bulk_idempotency.php',
    ];

    public function __construct(private VtuServiceRegistry $registry)
    {
    }

    public function install(): void
    {
        foreach (self::MIGRATIONS as $migration) {
            $exit = Artisan::call('migrate', [
                '--path' => $migration,
                '--force' => true,
            ]);

            if ($exit !== 0) {
                throw new \RuntimeException('VTU addon migration failed. Check the application logs.');
            }
        }

        $this->registry->bootstrapCatalogue();
    }
}
