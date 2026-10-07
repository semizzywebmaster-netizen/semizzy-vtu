<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('insurance_policies',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('insurance_product_id')->constrained('insurance_products')->restrictOnDelete();$t->foreignId('insurance_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();$t->string('reference')->unique();$t->string('provider_reference')->nullable()->index();$t->string('status')->default('pending')->index();$t->string('currency',3)->default('NGN');$t->unsignedBigInteger('premium_minor');$t->unsignedBigInteger('coverage_minor')->nullable();$t->json('holder_snapshot');$t->json('product_snapshot');$t->json('provider_snapshot')->nullable();$t->timestamp('starts_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamp('issued_at')->nullable();$t->timestamp('cancelled_at')->nullable();$t->timestamps();$t->index(['user_id','status']);}); }
 public function down(): void { Schema::dropIfExists('insurance_policies'); }
};