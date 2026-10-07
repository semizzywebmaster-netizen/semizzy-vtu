<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('insurance_products',function(Blueprint $t){$t->id();$t->foreignId('insurance_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();$t->string('code')->unique();$t->string('name');$t->string('category');$t->text('description')->nullable();$t->string('currency',3)->default('NGN');$t->unsignedBigInteger('premium_minor')->default(0);$t->unsignedBigInteger('min_premium_minor')->nullable();$t->unsignedBigInteger('max_premium_minor')->nullable();$t->json('coverage')->nullable();$t->json('eligibility')->nullable();$t->json('metadata')->nullable();$t->boolean('active')->default(true);$t->timestamps();$t->index(['category','active']);}); }
 public function down(): void { Schema::dropIfExists('insurance_products'); }
};