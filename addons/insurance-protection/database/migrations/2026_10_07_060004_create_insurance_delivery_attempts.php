<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('insurance_delivery_attempts',function(Blueprint $t){$t->id();$t->foreignId('insurance_provider_id')->constrained('insurance_providers')->cascadeOnDelete();$t->foreignId('insurance_policy_id')->nullable()->constrained('insurance_policies')->nullOnDelete();$t->foreignId('insurance_claim_id')->nullable()->constrained('insurance_claims')->nullOnDelete();$t->unsignedInteger('attempt')->default(1);$t->string('operation');$t->string('status')->default('pending');$t->string('external_reference')->nullable();$t->text('response')->nullable();$t->text('error')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('completed_at')->nullable();$t->timestamps();$t->index(['operation','status']);}); }
 public function down(): void { Schema::dropIfExists('insurance_delivery_attempts'); }
};