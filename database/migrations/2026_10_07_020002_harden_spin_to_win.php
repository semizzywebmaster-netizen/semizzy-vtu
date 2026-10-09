<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('spin_campaigns', function(Blueprint $t){
   $t->unsignedInteger('total_play_limit')->nullable()->after('daily_play_limit');
   $t->json('tier_restrictions')->nullable()->after('eligibility');
  });
  Schema::table('spin_prizes', function(Blueprint $t){
   $t->string('coupon_code')->nullable()->after('currency');
   $t->json('eligibility')->nullable()->after('metadata');
  });
  Schema::table('spin_plays', function(Blueprint $t){
   $t->string('reward_event_key',190)->nullable()->after('operation_key')->unique();
   $t->timestamp('rewarded_at')->nullable()->after('played_at');
   $t->index(['campaign_id','status','played_at']);
  });
 }
 public function down(): void {
  Schema::table('spin_plays', function(Blueprint $t){$t->dropIndex(['campaign_id','status','played_at']);$t->dropUnique(['reward_event_key']);$t->dropColumn(['reward_event_key','rewarded_at']);});
  Schema::table('spin_prizes', function(Blueprint $t){$t->dropColumn(['coupon_code','eligibility']);});
  Schema::table('spin_campaigns', function(Blueprint $t){$t->dropColumn(['total_play_limit','tier_restrictions']);});
 }
};