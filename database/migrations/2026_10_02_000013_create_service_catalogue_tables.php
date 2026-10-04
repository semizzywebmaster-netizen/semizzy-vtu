<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('enabled')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['category_id', 'enabled']);
            });
        }

        if (! Schema::hasTable('service_products')) {
            Schema::create('service_products', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->string('key');
                $table->string('name');
                $table->string('provider_product_id')->nullable();
                $table->decimal('provider_cost', 20, 6)->nullable();
                $table->string('currency', 3)->default('NGN');
                $table->json('metadata')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->unique(['service_id', 'key']);
            });
        }

        if (! Schema::hasTable('provider_service_products')) {
            Schema::create('provider_service_products', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
                $table->foreignId('service_product_id')->constrained('service_products')->cascadeOnDelete();
                $table->string('provider_product_id')->nullable();
                $table->decimal('provider_cost', 20, 6)->nullable();
                $table->string('currency', 3)->default('NGN');
                $table->json('raw_catalogue')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('provider_service_products')) {
            $indexes = Schema::getIndexes('provider_service_products');
            $hasProviderProductUnique = collect($indexes)->contains(
                fn (array $index): bool =>
                    ($index['name'] ?? '') === 'provider_service_products_provider_product_unique'
                    || (($index['unique'] ?? false)
                        && ($index['columns'] ?? []) === ['api_provider_id', 'service_product_id'])
            );

            if (! $hasProviderProductUnique) {
                Schema::table('provider_service_products', function (Blueprint $table): void {
                    $table->unique(
                        ['api_provider_id', 'service_product_id'],
                        'provider_service_products_provider_product_unique'
                    );
                });
            }
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('provider_service_products');
        Schema::dropIfExists('service_products');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');

        Schema::enableForeignKeyConstraints();
    }
};
