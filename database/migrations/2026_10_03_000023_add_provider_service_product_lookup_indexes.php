<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_service_products', function (Blueprint $table): void {
            $table->index(['api_provider_id', 'enabled']);
            $table->index(['service_product_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_service_products', function (Blueprint $table): void {
            $table->dropIndex(['api_provider_id', 'enabled']);
            $table->dropIndex(['service_product_id', 'enabled']);
        });
    }
};
