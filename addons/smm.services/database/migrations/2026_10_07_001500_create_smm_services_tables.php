<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('smm_service_categories', function(Blueprint $t){
            $t->id(); $t->string('key')->unique(); $t->string('name'); $t->text('description')->nullable();
            $t->boolean('active')->default(true); $t->json('metadata')->nullable(); $t->timestamps();
        });
        Schema::create('smm_services', function(Blueprint $t){
            $t->id(); $t->foreignId('category_id')->constrained('smm_service_categories')->cascadeOnDelete();
            $t->string('service_key')->unique(); $t->string('name'); $t->string('platform');
            $t->string('operation')->default('smm_order'); $t->string('mode')->default('provider_api');
            $t->unsignedBigInteger('min_quantity')->default(1); $t->unsignedBigInteger('max_quantity')->default(1);
            $t->unsignedBigInteger('unit_price_minor')->default(0); $t->string('currency',3)->default('NGN');
            $t->boolean('active')->default(false); $t->json('requirements')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
            $t->index(['platform','active']); $t->index(['mode','active']);
        });
        Schema::create('smm_orders', function(Blueprint $t){
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->foreignId('service_id')->constrained('smm_services');
            $t->string('reference')->unique(); $t->string('idempotency_key'); $t->unsignedBigInteger('quantity');
            $t->unsignedBigInteger('amount_minor'); $t->string('currency',3)->default('NGN'); $t->string('status')->default('pending');
            $t->string('provider_reference')->nullable(); $t->text('target'); $t->json('metadata')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->timestamps();
            $t->unique(['user_id','idempotency_key']); $t->index(['user_id','status']); $t->index(['provider_reference']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('smm_orders'); Schema::dropIfExists('smm_services'); Schema::dropIfExists('smm_service_categories');
    }
};