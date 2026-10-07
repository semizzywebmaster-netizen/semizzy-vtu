<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('business_profiles',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('business_name');$t->string('business_type')->default('business');$t->string('registration_number')->nullable();$t->string('contact_phone',40)->nullable();$t->string('contact_email')->nullable();$t->string('status')->default('pending');$t->json('metadata')->nullable();$t->timestamps();$t->index(['status','business_type']);});
  Schema::create('business_partners',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('business_profile_id')->nullable()->constrained('business_profiles')->nullOnDelete();$t->string('type');$t->string('code')->unique();$t->string('status')->default('pending');$t->string('pricing_profile')->default('default');$t->unsignedBigInteger('daily_limit_minor')->nullable();$t->unsignedBigInteger('monthly_limit_minor')->nullable();$t->unsignedBigInteger('commission_rate_bps')->default(0);$t->json('metadata')->nullable();$t->timestamps();$t->index(['type','status']);$t->index(['user_id','type']);});
  Schema::create('business_partner_events',function(Blueprint $t){$t->id();$t->foreignId('business_partner_id')->constrained()->cascadeOnDelete();$t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('action');$t->string('status')->nullable();$t->text('note')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['business_partner_id','created_at']);});
 }
 public function down():void{Schema::dropIfExists('business_partner_events');Schema::dropIfExists('business_partners');Schema::dropIfExists('business_profiles');}
};