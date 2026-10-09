<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up(): void
 {
  Schema::create('ad_promotion_packages', function (Blueprint $table): void {
   $table->id();
   $table->string('key', 80)->unique();
   $table->string('name', 140);
   $table->text('description')->nullable();
   $table->unsignedSmallInteger('duration_days');
   $table->unsignedBigInteger('price_minor')->default(0);
   $table->string('currency', 3)->default('NGN');
   $table->unsignedSmallInteger('priority_weight')->default(1);
   $table->boolean('is_active')->default(false)->index();
   $table->json('eligible_target_types')->nullable();
   $table->timestamps();
  });

  Schema::create('ad_promotions', function (Blueprint $table): void {
   $table->id();
   $table->foreignId('advertiser_id')->constrained('users')->restrictOnDelete();
   $table->foreignId('package_id')->constrained('ad_promotion_packages')->restrictOnDelete();
   // Polymorphic target keeps the addon independent of Marketplace's internal listing table.
   $table->string('target_type', 80);
   $table->unsignedBigInteger('target_id');
   $table->string('target_label', 180)->nullable();
   $table->unsignedBigInteger('price_minor')->default(0);
   $table->string('currency', 3)->default('NGN');
   $table->string('status', 30)->default('pending_review')->index();
   $table->string('payment_status', 30)->default('unpaid')->index();
   $table->timestamp('starts_at')->nullable();
   $table->timestamp('ends_at')->nullable();
   $table->text('advertiser_note')->nullable();
   $table->text('admin_note')->nullable();
   $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamp('reviewed_at')->nullable();
   $table->timestamps();
   $table->index(['target_type','target_id','status']);
   $table->index(['advertiser_id','created_at']);
  });
 }

 public function down(): void
 {
  Schema::dropIfExists('ad_promotions');
  Schema::dropIfExists('ad_promotion_packages');
 }
};
