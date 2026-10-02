<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->json('manifest')->nullable()->after('settings_schema');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('manifest');
        });
    }
};
