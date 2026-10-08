<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('url', 2048);
            $table->string('disk', 100)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['product_id','media_type','sort_order']);
        });

        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->string('location_text', 255)->nullable()->after('category_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('location_text');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('shipping_mode', 30)->nullable()->after('requires_shipping');
            $table->string('fulfillment_policy', 30)->default('standard')->after('shipping_mode');
            $table->text('seller_terms')->nullable()->after('description');
            $table->text('buyer_requirements')->nullable()->after('seller_terms');
            $table->index(['latitude','longitude']);
        });

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->string('fulfillment_status', 30)->default('unfulfilled')->after('status')->index();
            $table->string('delivery_status', 30)->nullable()->after('fulfillment_status')->index();
            $table->json('shipping_address')->nullable()->after('note');
            $table->json('delivery_data')->nullable()->after('shipping_address');
            $table->timestamp('fulfilled_at')->nullable()->after('refunded_at');
            $table->timestamp('accepted_at')->nullable()->after('fulfilled_at');
            $table->timestamp('completed_at')->nullable()->after('accepted_at');
        });

        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->string('service_status', 30)->nullable()->after('delivery_status')->index();
            $table->unsignedInteger('revision_count')->default(0)->after('service_status');
            $table->text('buyer_requirements')->nullable()->after('revision_count');
            $table->text('seller_submission')->nullable()->after('buyer_requirements');
        });

        Schema::create('marketplace_service_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id','sequence']);
        });

        Schema::create('marketplace_digital_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->string('asset_type', 30)->default('download');
            $table->string('disk', 100)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('version', 80)->nullable();
            $table->string('checksum', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['product_id','active']);
        });

        Schema::create('marketplace_digital_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('marketplace_digital_assets')->nullOnDelete();
            $table->string('delivery_token', 100)->unique();
            $table->unsignedInteger('download_limit')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['order_id','revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_digital_deliveries');
        Schema::dropIfExists('marketplace_digital_assets');
        Schema::dropIfExists('marketplace_service_milestones');
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn(['fulfillment_status','delivery_status','shipping_address','delivery_data','fulfilled_at','accepted_at','completed_at','service_status','revision_count','buyer_requirements','seller_submission']);
        });
        Schema::table('marketplace_products', function (Blueprint $table): void {
            $table->dropIndex('marketplace_products_latitude_longitude_index');
            $table->dropColumn(['location_text','latitude','longitude','shipping_mode','fulfillment_policy','seller_terms','buyer_requirements']);
        });
        Schema::dropIfExists('marketplace_product_media');
    }
};
