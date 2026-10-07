<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('insurance_providers',function(Blueprint $t){$t->id();$t->string('name');$t->string('driver')->nullable();$t->text('credentials')->nullable();$t->json('capabilities')->nullable();$t->unsignedInteger('priority')->default(100);$t->unsignedInteger('weight')->default(100);$t->boolean('enabled')->default(true);$t->boolean('paused')->default(false);$t->unsignedInteger('failure_count')->default(0);$t->timestamp('cooldown_until')->nullable();$t->timestamp('last_success_at')->nullable();$t->timestamp('last_failure_at')->nullable();$t->timestamps();$t->index(['enabled','paused','priority']);}); }
 public function down(): void { Schema::dropIfExists('insurance_providers'); }
};