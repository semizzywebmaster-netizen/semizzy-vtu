<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('business_partners',function(Blueprint $t){$t->foreignId('parent_partner_id')->nullable()->after('business_profile_id')->constrained('business_partners')->nullOnDelete();$t->string('settlement_mode')->default('wallet')->after('commission_rate_bps');$t->unsignedBigInteger('minimum_balance_minor')->default(0)->after('settlement_mode');$t->json('service_rules')->nullable()->after('metadata');$t->index(['parent_partner_id','status']);});
  Schema::create('business_pricing_rules',function(Blueprint $t){$t->id();$t->foreignId('business_partner_id')->constrained('business_partners')->cascadeOnDelete();$t->string('service_key');$t->string('product_key')->nullable();$t->string('rule_type')->default('markup');$t->unsignedBigInteger('amount_minor')->nullable();$t->unsignedInteger('rate_bps')->nullable();$t->unsignedBigInteger('min_amount_minor')->nullable();$t->unsignedBigInteger('max_amount_minor')->nullable();$t->boolean('enabled')->default(true);$t->json('metadata')->nullable();$t->timestamps();$t->index(['business_partner_id','service_key','enabled']);});
  Schema::create('business_limit_counters',function(Blueprint $t){$t->id();$t->foreignId('business_partner_id')->constrained('business_partners')->cascadeOnDelete();$t->date('period_date');$t->date('period_month');$t->unsignedBigInteger('daily_used_minor')->default(0);$t->unsignedBigInteger('monthly_used_minor')->default(0);$t->timestamps();$t->unique(['business_partner_id','period_date']);$t->index(['business_partner_id','period_month']);});
 }
 public function down():void{Schema::dropIfExists('business_limit_counters');Schema::dropIfExists('business_pricing_rules');Schema::table('business_partners',function(Blueprint $t){$t->dropConstrainedForeignId('parent_partner_id');$t->dropColumn(['settlement_mode','minimum_balance_minor','service_rules']);});}
};