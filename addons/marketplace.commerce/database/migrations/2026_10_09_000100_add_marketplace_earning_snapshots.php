<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_earnings', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('seller_id')->constrained('marketplace_categories')->nullOnDelete();
            $table->string('gross_amount_minor', 30)->nullable()->after('net_minor');
            $table->unsignedInteger('category_profit_bps')->default(0);
            $table->string('category_profit_fixed_minor', 30)->default('0');
            $table->string('platform_profit_minor', 30)->default('0');
            $table->string('seller_net_minor', 30)->nullable();
            $table->json('calculation_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_earnings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'gross_amount_minor',
                'category_profit_bps',
                'category_profit_fixed_minor',
                'platform_profit_minor',
                'seller_net_minor',
                'calculation_snapshot',
            ]);
        });
    }
};
