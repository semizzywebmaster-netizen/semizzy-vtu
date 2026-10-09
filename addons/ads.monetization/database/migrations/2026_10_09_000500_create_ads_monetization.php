<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void
 {
  Schema::create('ad_placements', function (Blueprint $table): void {
   $table->id();
   $table->string('key', 100)->unique();
   $table->string('name', 160);
   $table->string('surface', 80)->index();
   $table->string('format', 40)->default('native');
   $table->text('description')->nullable();
   $table->boolean('is_active')->default(false)->index();
   $table->json('rules')->nullable();
   $table->timestamps();
  });

  Schema::create('ad_campaigns', function (Blueprint $table): void {
   $table->id();
   $table->foreignId('advertiser_id')->constrained('users')->restrictOnDelete();
   $table->string('name', 180);
   $table->string('objective', 30)->default('traffic');
   $table->string('billing_model', 10)->default('cpc');
   $table->string('status', 30)->default('draft')->index();
   $table->string('review_status', 30)->default('pending')->index();
   $table->string('currency', 3)->default('NGN');
   $table->unsignedBigInteger('budget_minor')->default(0);
   $table->unsignedBigInteger('daily_budget_minor')->default(0);
   $table->unsignedBigInteger('spent_minor')->default(0);
   $table->unsignedBigInteger('bid_minor')->default(0);
   $table->timestamp('starts_at')->nullable();
   $table->timestamp('ends_at')->nullable();
   $table->json('targeting')->nullable();
   $table->text('admin_note')->nullable();
   $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamp('reviewed_at')->nullable();
   $table->timestamps();
   $table->index(['status','starts_at','ends_at']);
   $table->index(['advertiser_id','created_at']);
  });

  Schema::create('ad_creatives', function (Blueprint $table): void {
   $table->id();
   $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
   $table->foreignId('placement_id')->nullable()->constrained('ad_placements')->nullOnDelete();
   $table->string('headline', 180);
   $table->text('body')->nullable();
   $table->string('destination_url', 2048);
   $table->string('image_path', 2048)->nullable();
   $table->string('status', 30)->default('pending')->index();
   $table->timestamps();
   $table->index(['campaign_id','status']);
  });

  Schema::create('ad_events', function (Blueprint $table): void {
   $table->id();
   $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
   $table->foreignId('creative_id')->nullable()->constrained('ad_creatives')->nullOnDelete();
   $table->foreignId('placement_id')->nullable()->constrained('ad_placements')->nullOnDelete();
   $table->foreignId('viewer_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('event_type', 20)->index();
   $table->string('event_key', 64)->unique();
   $table->string('session_hash', 64)->nullable()->index();
   $table->string('ip_hash', 64)->nullable()->index();
   $table->string('user_agent_hash', 64)->nullable();
   $table->unsignedBigInteger('billable_minor')->default(0);
   $table->json('metadata')->nullable();
   $table->timestamp('occurred_at')->index();
   $table->timestamps();
   $table->index(['campaign_id','event_type','occurred_at']);
  });

  Schema::create('ad_settings', function (Blueprint $table): void {
   $table->id();
   $table->string('key', 120)->unique();
   $table->json('value')->nullable();
   $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamps();
  });
 }
 public function down(): void
 {
  Schema::dropIfExists('ad_settings');
  Schema::dropIfExists('ad_events');
  Schema::dropIfExists('ad_creatives');
  Schema::dropIfExists('ad_campaigns');
  Schema::dropIfExists('ad_placements');
 }
};
