<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_categories', function (Blueprint $table) {
            $table->unsignedInteger('sale_profit_bps')->default(0)->after('listing_type');
            $table->string('sale_profit_fixed_minor', 30)->default('0')->after('sale_profit_bps');
            $table->index('sale_profit_bps');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_categories', function (Blueprint $table) {
            $table->dropIndex('marketplace_categories_sale_profit_bps_index');
            $table->dropColumn(['sale_profit_bps', 'sale_profit_fixed_minor']);
        });
    }
};
