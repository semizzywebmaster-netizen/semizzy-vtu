<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->index('device_key', 'user_devices_device_key_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->dropIndex('user_devices_device_key_index');
        });
    }
};