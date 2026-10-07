<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('mailer_smtp_profiles',function(Blueprint $t){
   $t->string('provider',40)->default('custom')->after('name');
   $t->timestamp('cooldown_until')->nullable()->after('failure_count');
   $t->timestamp('last_health_check_at')->nullable()->after('last_failure_at');
   $t->string('health_status',20)->default('unknown')->after('last_health_check_at');
   $t->string('last_error',500)->nullable()->change();
   $t->index(['enabled','priority','cooldown_until']);
  });
  Schema::create('mailer_smtp_attempts',function(Blueprint $t){
   $t->id();$t->foreignId('profile_id')->constrained('mailer_smtp_profiles')->cascadeOnDelete();
   $t->string('operation_key',190)->nullable();$t->string('status',20);$t->string('error',500)->nullable();
   $t->timestamp('attempted_at');$t->timestamps();$t->index(['operation_key','attempted_at']);
  });
 }
 public function down():void{
  Schema::dropIfExists('mailer_smtp_attempts');
  Schema::table('mailer_smtp_profiles',function(Blueprint $t){$t->dropIndex(['enabled','priority','cooldown_until']);$t->dropColumn(['provider','cooldown_until','last_health_check_at','health_status']);});
 }
};