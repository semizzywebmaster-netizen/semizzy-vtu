<?php

namespace App\Services\Vtu;

use App\Models\Addon;

class VtuAddonInstaller
{
    public function install(Addon $addon): void
    {
        app(VtuServiceRegistry::class)->bootstrapCatalogue();
    }
}
