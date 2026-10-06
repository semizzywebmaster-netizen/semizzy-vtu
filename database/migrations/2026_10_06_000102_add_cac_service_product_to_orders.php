<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cac_orders', function (Blueprint $table) {
            $table->foreignId('cac_service_product_id')->nullable()->after('user_id')->constrained('cac_service_products')->nullOnDelete();
            $table->index(['cac_service_product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('cac_orders', function (Blueprint $table) {
            $table->dropForeign(['cac_service_product_id']);
            $table->dropIndex(['cac_service_product_id', 'status']);
            $table->dropColumn('cac_service_product_id');
        });
    }
};
