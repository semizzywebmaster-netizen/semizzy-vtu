<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cac_service_products')) {
            Schema::create('cac_service_products', function (Blueprint $t) {
                $t->id();
                $t->string('identifier')->unique();
                $t->string('name');
                $t->string('service_type')->index();
                $t->text('description')->nullable();
                $t->string('currency', 3)->default('NGN');
                $t->decimal('provider_price_minor', 20, 0)->nullable();
                $t->decimal('selling_price_minor', 20, 0)->nullable();
                $t->boolean('enabled')->default(false);
                $t->json('requirements')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cac_provider_routes')) {
            Schema::create('cac_provider_routes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cac_service_product_id')->constrained('cac_service_products')->cascadeOnDelete();
                $t->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
                $t->unsignedInteger('priority')->default(100);
                $t->boolean('enabled')->default(true);
                $t->json('endpoint_map')->nullable();
                $t->json('capabilities')->nullable();
                $t->timestamps();
                // Keep the explicit name under MySQL's 64-character identifier limit.
                $t->unique(['cac_service_product_id', 'api_provider_id'], 'cac_route_product_provider_uq');
                $t->index(['cac_service_product_id', 'enabled', 'priority'], 'cac_route_product_state_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cac_provider_routes');
        Schema::dropIfExists('cac_service_products');
    }
};
