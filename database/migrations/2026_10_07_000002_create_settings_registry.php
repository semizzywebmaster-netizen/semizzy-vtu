<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        $service = app(\App\Services\System\SettingsService::class);

        foreach ($service->definitions() as $key => $definition) {
            $service->register($key, $definition);
        }
    }

    public function down(): void
    {
        // Registry metadata is intentionally retained. It may contain addon settings
        // and deleting it during rollback could remove administrator configuration.
    }
};
