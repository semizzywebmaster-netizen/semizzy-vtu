<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('social_account_inventory', function(Blueprint $t){
   $t->id(); $t->string('platform',60); $t->string('title',160); $t->string('username',160)->nullable();
   $t->string('country_code',8)->nullable(); $t->unsignedInteger('account_age_days')->nullable();
   $t->unsignedBigInteger('followers')->nullable(); $t->string('niche',120)->nullable();
   $t->text('description')->nullable(); $t->string('fulfillment_mode',20)->default('manual');
   $t->string('provider_reference')->nullable(); $t->decimal('price',20,2); $t->string('currency',3)->default('NGN');
   $t->string('status',20)->default('available'); $t->json('credentials')->nullable(); $t->json('metadata')->nullable();
   $t->timestamps(); $t->index(['platform','status']); $t->index(['fulfillment_mode','status']);
  });
  Schema::create('social_number_inventory', function(Blueprint $t){
   $t->id(); $t->string('country_code',8); $t->string('country_name',100); $t->string('service_key',120)->nullable();
   $t->string('phone_number',64); $t->string('phone_hash',64)->unique(); $t->string('fulfillment_mode',20)->default('manual');
   $t->string('provider_reference')->nullable(); $t->decimal('price',20,2); $t->string('currency',3)->default('NGN');
   $t->string('status',20)->default('available'); $t->timestamp('expires_at')->nullable();
   $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['country_code','status']); $t->index(['service_key','status']);
  });
  Schema::create('social_service_orders', function(Blueprint $t){
   $t->id(); $t->string('reference',40)->unique(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->string('order_type',20); $t->unsignedBigInteger('inventory_id')->nullable(); $t->string('status',30)->default('pending_payment');
   $t->string('payment_reference',100)->nullable()->unique(); $t->string('payment_status',20)->default('pending'); $t->timestamp('paid_at')->nullable();
   $t->decimal('amount',20,2); $t->string('currency',3)->default('NGN'); $t->string('provider_reference')->nullable();
   $t->string('provider_id')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->timestamp('expires_at')->nullable();
   $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['user_id','status']); $t->index(['order_type','inventory_id']);
  });
  Schema::create('social_number_sms', function(Blueprint $t){
   $t->id(); $t->foreignId('order_id')->constrained('social_service_orders')->cascadeOnDelete();
   $t->string('sender',160)->nullable(); $t->text('message'); $t->string('provider_message_id',160)->nullable();
   $t->timestamp('received_at'); $t->timestamp('read_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['order_id','received_at']); $t->index('provider_message_id');
  });
  Schema::create('social_service_settings', function(Blueprint $t){
   $t->id(); $t->string('key',100)->unique(); $t->text('value')->nullable(); $t->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('social_number_sms'); Schema::dropIfExists('social_service_settings');
  Schema::dropIfExists('social_service_orders'); Schema::dropIfExists('social_number_inventory'); Schema::dropIfExists('social_account_inventory');
 }
};