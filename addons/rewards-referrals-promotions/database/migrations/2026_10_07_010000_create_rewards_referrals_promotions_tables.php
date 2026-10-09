<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('reward_referral_codes', function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->string('code',80)->unique(); $t->boolean('active')->default(true);
   $t->unsignedInteger('uses_count')->default(0); $t->unsignedInteger('max_uses')->nullable();
   $t->timestamps(); $t->index(['user_id','active']);
  });
  Schema::create('reward_referrals', function(Blueprint $t){
   $t->id(); $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
   $t->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
   $t->foreignId('referral_code_id')->nullable()->constrained('reward_referral_codes')->nullOnDelete();
   $t->string('status',30)->default('pending'); $t->string('source',40)->default('code');
   $t->timestamp('qualified_at')->nullable(); $t->timestamp('rewarded_at')->nullable();
   $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['referrer_id','referred_id']); $t->index(['referrer_id','status']);
  });
  Schema::create('reward_rules', function(Blueprint $t){
   $t->id(); $t->string('name',160); $t->string('event_key',100);
   $t->string('reward_type',30)->default('fixed'); $t->decimal('amount',18,2)->default(0);
   $t->char('currency',3)->default('NGN'); $t->boolean('active')->default(true);
   $t->unsignedInteger('max_global_redemptions')->nullable(); $t->unsignedInteger('max_user_redemptions')->nullable();
   $t->json('conditions')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['event_key','active']);
  });
  Schema::create('reward_events', function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $t->foreignId('rule_id')->nullable()->constrained('reward_rules')->nullOnDelete();
   $t->string('event_key',100); $t->string('operation_key',180)->unique();
   $t->string('status',30)->default('pending');
   $t->string('approval_status',20)->default('pending'); $t->unsignedBigInteger('approved_by')->nullable();
   $t->timestamp('approved_at')->nullable(); $t->text('approval_note')->nullable();
   $t->decimal('amount',18,2)->default(0);
   $t->char('currency',3)->default('NGN'); $t->string('wallet_reference',120)->nullable();
   $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['user_id','event_key','status']); $t->index(['approval_status','event_key']);
  });
  Schema::create('reward_campaigns', function(Blueprint $t){
   $t->id(); $t->string('name',180); $t->string('code',100)->unique()->nullable();
   $t->string('type',40)->default('promotion'); $t->string('status',30)->default('draft');
   $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable();
   $t->json('rules')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['status','starts_at','ends_at']);
  });
  Schema::create('reward_coupons', function(Blueprint $t){
   $t->id(); $t->foreignId('campaign_id')->nullable()->constrained('reward_campaigns')->nullOnDelete();
   $t->string('code',100)->unique(); $t->string('discount_type',30)->default('fixed');
   $t->decimal('discount_value',18,2)->default(0); $t->char('currency',3)->default('NGN');
   $t->unsignedInteger('max_redemptions')->nullable(); $t->unsignedInteger('per_user_limit')->nullable();
   $t->unsignedInteger('redemptions_count')->default(0); $t->boolean('active')->default(true);
   $t->timestamp('starts_at')->nullable(); $t->timestamp('ends_at')->nullable(); $t->json('conditions')->nullable(); $t->timestamps();
   $t->index(['active','starts_at','ends_at']);
  });
  Schema::create('reward_coupon_redemptions', function(Blueprint $t){
   $t->id(); $t->foreignId('coupon_id')->constrained('reward_coupons')->cascadeOnDelete();
   $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('operation_key',180)->unique();
   $t->decimal('discount_value',18,2)->default(0); $t->char('currency',3)->default('NGN'); $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['coupon_id','user_id','operation_key']); $t->index(['coupon_id','user_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('reward_coupon_redemptions'); Schema::dropIfExists('reward_coupons');
  Schema::dropIfExists('reward_campaigns'); Schema::dropIfExists('reward_events');
  Schema::dropIfExists('reward_rules'); Schema::dropIfExists('reward_referrals'); Schema::dropIfExists('reward_referral_codes');
 }
};