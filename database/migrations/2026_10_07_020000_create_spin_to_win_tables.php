<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('spin_campaigns', function(Blueprint $t){$t->id();$t->string('name');$t->string('status',20)->default('draft')->index();$t->timestamp('starts_at')->nullable();$t->timestamp('ends_at')->nullable();$t->unsignedInteger('daily_play_limit')->default(1);$t->json('eligibility')->nullable();$t->json('metadata')->nullable();$t->timestamps();});
  Schema::create('spin_prizes', function(Blueprint $t){$t->id();$t->foreignId('campaign_id')->constrained('spin_campaigns')->cascadeOnDelete();$t->string('name');$t->string('prize_type',30)->default('wallet');$t->string('amount',30)->default('0');$t->string('currency',3)->default('NGN');$t->decimal('weight',12,6)->default(0);$t->unsignedInteger('max_wins')->nullable();$t->unsignedInteger('wins_count')->default(0);$t->boolean('active')->default(true);$t->json('metadata')->nullable();$t->timestamps();$t->index(['campaign_id','active']);});
  Schema::create('spin_plays', function(Blueprint $t){$t->id();$t->foreignId('campaign_id')->constrained('spin_campaigns')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('prize_id')->nullable()->constrained('spin_prizes')->nullOnDelete();$t->string('operation_key',190)->unique();$t->string('status',30)->default('pending')->index();$t->timestamp('played_at');$t->json('metadata')->nullable();$t->timestamps();$t->index(['user_id','campaign_id','played_at']);});
 }
 public function down(): void {Schema::dropIfExists('spin_plays');Schema::dropIfExists('spin_prizes');Schema::dropIfExists('spin_campaigns');}
};