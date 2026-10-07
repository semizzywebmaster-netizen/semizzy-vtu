<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('marketplace_products', function(Blueprint $table){
   $table->id(); $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
   $table->string('name',180); $table->string('slug',220)->unique(); $table->text('description')->nullable();
   $table->string('category',100)->index(); $table->string('currency',3)->default('NGN');
   $table->string('price_minor',30); $table->string('stock_quantity',30)->default('0');
   $table->string('status',20)->default('draft')->index(); $table->json('metadata')->nullable(); $table->timestamps();
   $table->index(['seller_id','status']);
  });
  Schema::create('marketplace_orders', function(Blueprint $table){
   $table->id(); $table->string('reference',40)->unique();
   $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
   $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
   $table->foreignId('product_id')->constrained('marketplace_products')->restrictOnDelete();
   $table->string('idempotency_key',120)->nullable(); $table->string('quantity',30);
   $table->string('unit_price_minor',30); $table->string('total_minor',30); $table->string('currency',3);
   $table->string('status',30)->default('pending')->index(); $table->text('note')->nullable();
   $table->json('metadata')->nullable(); $table->timestamps();
   $table->unique(['buyer_id','idempotency_key']); $table->index(['seller_id','status']); $table->index(['buyer_id','created_at']);
  });
  Schema::create('marketplace_reviews', function(Blueprint $table){
   $table->id(); $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
   $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
   $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
   $table->unsignedTinyInteger('rating'); $table->text('comment')->nullable(); $table->timestamps();
   $table->unique(['order_id','buyer_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('marketplace_reviews'); Schema::dropIfExists('marketplace_orders'); Schema::dropIfExists('marketplace_products');
 }
};