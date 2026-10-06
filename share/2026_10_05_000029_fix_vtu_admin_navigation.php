<?php

use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Addon::query()
            ->where('identifier', 'vtu.digital-services')
            ->update([
                'navigation' => [
                    [
                        'id' => 'vtu',
                        'label' => 'VTU',
                        'url' => '/admin/vtu',
                        'icon' => 'server',
                        'permission' => 'vtu.view',
                        'section' => 'addons',
                        'order' => 10,
                    ],
                ],
            ]);
    }

    public function down(): void
    {
        Addon::query()
            ->where('identifier', 'vtu.digital-services')
            ->update([
                'navigation' => [
                    [
                        'id' => 'vtu',
                        'label' => 'VTU',
                        'url' => '/vtu',
                        'permission' => 'vtu.view',
                    ],
                ],
            ]);
    }
};
