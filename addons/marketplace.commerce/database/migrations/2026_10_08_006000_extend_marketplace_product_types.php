<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->string('product_type', 30)->default('physical')->after('category')->index();
            $table->string('condition', 30)->nullable()->after('product_type')->index();
            $table->string('delivery_type', 30)->default('seller_fulfilled')->after('condition');
            $table->boolean('requires_shipping')->default(true)->after('delivery_type');
            $table->unsignedInteger('download_limit')->nullable()->after('requires_shipping');
            $table->unsignedInteger('service_delivery_days')->nullable()->after('download_limit');
            $table->string('service_model', 30)->nullable()->after('service_delivery_days');
            $table->timestamp('published_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->dropColumn([
                'product_type',
                'condition',
                'delivery_type',
                'requires_shipping',
                'download_limit',
                'service_delivery_days',
                'service_model',
                'published_at',
            ]);
        });
    }
};