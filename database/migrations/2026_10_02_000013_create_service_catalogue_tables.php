<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('service_categories',function(Blueprint $t):void{$t->id();$t->string('key')->unique();$t->string('name');$t->text('description')->nullable();$t->boolean('enabled')->default(true);$t->unsignedInteger('sort_order')->default(100);$t->timestamps();});
  Schema::create('services',function(Blueprint $t):void{$t->id();$t->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();$t->string('key')->unique();$t->string('name');$t->text('description')->nullable();$t->boolean('enabled')->default(true);$t->json('metadata')->nullable();$t->timestamps();$t->index(['category_id','enabled']);});
  Schema::create('service_products',function(Blueprint $t):void{$t->id();$t->foreignId('service_id')->constrained('services')->cascadeOnDelete();$t->string('key');$t->string('name');$t->string('provider_product_id')->nullable();$t->decimal('provider_cost',20,6)->nullable();$t->string('currency',3)->default('NGN');$t->json('metadata')->nullable();$t->boolean('enabled')->default(true);$t->timestamps();$t->unique(['service_id','key']);});
  Schema::create('provider_service_products',function(Blueprint $t):void{$t->id();$t->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();$t->foreignId('service_product_id')->constrained('service_products')->cascadeOnDelete();$t->string('provider_product_id')->nullable();$t->decimal('provider_cost',20,6)->nullable();$t->string('currency',3)->default('NGN');$t->json('raw_catalogue')->nullable();$t->boolean('enabled')->default(true);$t->timestamp('last_synced_at')->nullable();$t->timestamps();$t->unique(['api_provider_id','service_product_id']);});
 }
 public function down(): void { Schema::dropIfExists('provider_service_products');Schema::dropIfExists('service_products');Schema::dropIfExists('services');Schema::dropIfExists('service_categories');}
};
