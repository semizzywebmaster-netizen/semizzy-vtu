<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('system_settings', 'theme_key')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->string('theme_key')->nullable()->after('key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_settings', 'theme_key')) {
            Schema::table('system_settings', function (Blueprint $table): void {
                $table->dropColumn('theme_key');
            });
        }
    }
};
