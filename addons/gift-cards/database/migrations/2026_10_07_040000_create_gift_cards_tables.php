<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void
 {
  Schema::create('gift_card_products', function (Blueprint $table) {
   $table->id();
   $table->string('name');
   $table->string('brand');
   $table->string('code')->unique();
   $table->string('country_code', 8)->nullable()->index();
   $table->string('currency', 8)->default('USD');
   $table->enum('denomination_type', ['fixed','variable'])->default('fixed');
   $table->json('denominations')->nullable();
   $table->decimal('min_amount', 20, 2)->nullable();
   $table->decimal('max_amount', 20, 2)->nullable();
   $table->decimal('provider_price', 20, 2)->default(0);
   $table->decimal('sale_price', 20, 2)->default(0);
   $table->string('fulfillment_mode')->default('provider');
   $table->json('metadata')->nullable();
   $table->boolean('enabled')->default(true)->index();
   $table->timestamps();
  });

  Schema::create('gift_card_inventory', function (Blueprint $table) {
   $table->id();
   $table->foreignId('gift_card_product_id')->constrained('gift_card_products')->cascadeOnDelete();
   $table->text('code_encrypted');
   $table->text('pin_encrypted')->nullable();
   $table->string('serial_hash', 64)->nullable()->unique();
   $table->string('status')->default('available')->index();
   $table->timestamp('sold_at')->nullable();
   $table->foreignId('sold_to_user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->index(['gift_card_product_id','status']);
  });

  Schema::create('gift_card_orders', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $table->foreignId('gift_card_product_id')->constrained('gift_card_products')->restrictOnDelete();
   $table->string('status')->default('pending')->index();
   $table->string('idempotency_key')->unique();
   $table->string('currency', 8)->default('NGN');
   $table->decimal('face_value', 20, 2);
   $table->decimal('fee', 20, 2)->default(0);
   $table->decimal('total', 20, 2);
   $table->string('provider_code')->nullable()->index();
   $table->string('provider_reference')->nullable()->index();
   $table->string('order_reference')->unique();
   $table->json('request_data')->nullable();
   $table->json('provider_response')->nullable();
   $table->json('delivery_data')->nullable();
   $table->text('failure_reason')->nullable();
   $table->timestamp('fulfilled_at')->nullable();
   $table->timestamp('failed_at')->nullable();
   $table->timestamps();
   $table->index(['user_id','status']);
  });

  Schema::create('gift_card_attempts', function (Blueprint $table) {
   $table->id();
   $table->foreignId('gift_card_order_id')->constrained('gift_card_orders')->cascadeOnDelete();
   $table->string('provider_code')->nullable()->index();
   $table->string('operation');
   $table->string('status')->index();
   $table->string('provider_reference')->nullable();
   $table->text('error')->nullable();
   $table->json('response')->nullable();
   $table->timestamps();
   $table->index(['gift_card_order_id','operation']);
  });

  Schema::create('gift_card_refunds', function (Blueprint $table) {
   $table->id();
   $table->foreignId('gift_card_order_id')->unique()->constrained('gift_card_orders')->cascadeOnDelete();
   $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $table->decimal('amount', 20, 2);
   $table->string('status')->default('pending')->index();
   $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
   $table->text('note')->nullable();
   $table->timestamps();
  });
 }

 public function down(): void
 {
  Schema::dropIfExists('gift_card_refunds');
  Schema::dropIfExists('gift_card_attempts');
  Schema::dropIfExists('gift_card_orders');
  Schema::dropIfExists('gift_card_inventory');
  Schema::dropIfExists('gift_card_products');
 }
};