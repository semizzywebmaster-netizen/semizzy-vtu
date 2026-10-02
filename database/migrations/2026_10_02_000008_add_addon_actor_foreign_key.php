<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('addon_lifecycle_events') && Schema::hasTable('users')) {
            Schema::table('addon_lifecycle_events', function (Blueprint $table): void {
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addon_lifecycle_events')) {
            Schema::table('addon_lifecycle_events', function (Blueprint $table): void {
                $table->dropForeign(['actor_id']);
            });
        }
    }
};
