<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('mailer_smtp_profiles', function(Blueprint $t){$t->id();$t->string('profile_key',80)->unique();$t->string('name');$t->string('host');$t->unsignedSmallInteger('port')->default(587);$t->string('encryption',20)->nullable();$t->string('username')->nullable();$t->text('password')->nullable();$t->string('from_address');$t->string('from_name')->nullable();$t->boolean('enabled')->default(true);$t->unsignedInteger('priority')->default(100);$t->unsignedInteger('weight')->default(1);$t->unsignedInteger('failure_count')->default(0);$t->string('health_status',30)->default('unknown');$t->timestamp('cooldown_until')->nullable();$t->timestamp('last_health_check_at')->nullable();$t->timestamp('last_success_at')->nullable();$t->timestamp('last_failure_at')->nullable();$t->text('last_error')->nullable();$t->timestamps();$t->index(['enabled','priority']);});
 }
 public function down(): void {Schema::dropIfExists('mailer_smtp_profiles');}
};