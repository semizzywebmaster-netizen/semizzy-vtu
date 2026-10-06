<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('name', 120)->default('Default Connection');
            $table->string('environment', 30)->default('sandbox');
            $table->string('base_url', 2048);
            $table->string('api_version', 100)->nullable();
            $table->string('api_prefix', 255)->nullable();
            $table->unsignedInteger('connect_timeout_seconds')->default(10);
            $table->unsignedInteger('request_timeout_seconds')->default(30);
            $table->boolean('verify_ssl')->default(true);
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->json('proxy')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 40)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['api_provider_id', 'environment']);
        });

        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_connection_id')->constrained('provider_connections')->cascadeOnDelete();
            $table->string('field_key', 120);
            $table->string('label', 160);
            $table->string('field_type', 40)->default('text');
            $table->boolean('required')->default(false);
            $table->boolean('secret')->default(true);
            $table->string('placement', 40)->default('header');
            $table->string('header_name', 255)->nullable();
            $table->string('query_name', 255)->nullable();
            $table->string('body_path', 255)->nullable();
            $table->string('prefix', 100)->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['provider_connection_id', 'field_key']);
        });

        Schema::create('provider_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('operation', 100)->nullable();
            $table->string('method', 10)->default('GET');
            $table->string('path', 2048)->nullable();
            $table->string('full_url', 2048)->nullable();
            $table->string('content_type', 60)->default('json');
            $table->string('auth_mode', 40)->default('connection');
            $table->json('headers')->nullable();
            $table->json('query_params')->nullable();
            $table->json('request_mapping')->nullable();
            $table->json('response_mapping')->nullable();
            $table->json('error_mapping')->nullable();
            $table->json('webhook_config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['api_provider_id', 'operation']);
        });

        Schema::create('provider_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('external_name', 255);
            $table->string('normalized_key', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 40)->default('discovered');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['api_provider_id', 'external_id']);
            $table->index(['api_provider_id', 'normalized_key']);
        });

        Schema::create('provider_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_category_id')->constrained('provider_categories')->cascadeOnDelete();
            $table->string('external_id', 255)->nullable();
            $table->string('external_name', 255);
            $table->string('normalized_key', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 40)->default('discovered');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_category_id', 'external_id']);
        });

        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->foreignId('provider_category_id')->nullable()->constrained('provider_categories')->nullOnDelete();
            $table->foreignId('provider_subcategory_id')->nullable()->constrained('provider_subcategories')->nullOnDelete();
            $table->string('external_service_id', 255);
            $table->string('external_service_code', 255)->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('service_type', 100)->nullable();
            $table->string('network', 100)->nullable();
            $table->decimal('provider_price', 20, 4)->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->string('status', 40)->default('discovered');
            $table->json('metadata')->nullable();
            $table->json('raw_provider_data')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['api_provider_id', 'external_service_id']);
            $table->index(['api_provider_id', 'status']);
        });

        Schema::create('provider_service_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->foreignId('provider_service_id')->constrained('provider_services')->cascadeOnDelete();
            $table->string('selection_scope', 40)->default('product');
            $table->boolean('imported')->default(false);
            $table->boolean('approved')->default(false);
            $table->boolean('auto_sync_allowed')->default(false);
            $table->string('state', 40)->default('awaiting_approval');
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();
            $table->unique(['api_provider_id', 'provider_service_id']);
        });

        Schema::create('provider_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('trigger', 40)->default('manual');
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('discovered_count')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('removed_count')->default(0);
            $table->unsignedInteger('price_changed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['api_provider_id', 'status']);
        });

        Schema::create('provider_product_mappings_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->foreignId('provider_service_id')->constrained('provider_services')->cascadeOnDelete();
            $table->unsignedBigInteger('catalogue_product_id')->nullable();
            $table->string('catalogue_product_type', 120)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(false);
            $table->string('mapping_status', 40)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider_service_id', 'catalogue_product_id', 'catalogue_product_type'], 'provider_product_map_unique');
            $table->index(['catalogue_product_id', 'priority']);
        });

        Schema::create('provider_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('scope_type', 40)->default('product');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('max_attempts')->default(1);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->boolean('enabled')->default(true);
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->index(['scope_type', 'scope_id', 'priority']);
        });

        Schema::create('provider_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->foreignId('provider_connection_id')->nullable()->constrained('provider_connections')->nullOnDelete();
            $table->string('status', 40);
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['api_provider_id', 'checked_at']);
        });

        Schema::create('provider_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->foreignId('provider_connection_id')->nullable()->constrained('provider_connections')->nullOnDelete();
            $table->string('operation', 120);
            $table->string('method', 10)->nullable();
            $table->string('endpoint', 2048)->nullable();
            $table->string('internal_reference', 120)->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('result', 40)->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('safe_message')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamps();
            $table->index(['api_provider_id', 'created_at']);
            $table->index('internal_reference');
        });
    }

    public function down(): void
    {
        foreach ([
            'provider_operation_logs',
            'provider_health_checks',
            'provider_routing_rules',
            'provider_product_mappings_v2',
            'provider_syncs',
            'provider_service_imports',
            'provider_services',
            'provider_subcategories',
            'provider_categories',
            'provider_endpoints',
            'provider_credentials',
            'provider_connections',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
