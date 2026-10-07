<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('insurance_claims',function(Blueprint $t){$t->id();$t->foreignId('insurance_policy_id')->constrained('insurance_policies')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('reference')->unique();$t->string('status')->default('submitted')->index();$t->string('claim_type')->nullable();$t->unsignedBigInteger('amount_minor')->nullable();$t->text('description');$t->json('documents')->nullable();$t->json('provider_snapshot')->nullable();$t->string('provider_reference')->nullable()->index();$t->timestamp('submitted_at')->nullable();$t->timestamp('resolved_at')->nullable();$t->timestamps();$t->index(['user_id','status']);}); }
 public function down(): void { Schema::dropIfExists('insurance_claims'); }
};