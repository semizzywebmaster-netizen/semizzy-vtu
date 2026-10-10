<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table): void {
            $table->decimal('balance_low_threshold', 20, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table): void {
            $table->dropColumn('balance_low_threshold');
        });
    }
};
