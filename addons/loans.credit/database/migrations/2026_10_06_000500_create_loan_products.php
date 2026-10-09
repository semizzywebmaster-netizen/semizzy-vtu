<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('loan_products',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->string('name');$t->string('currency',3)->default('NGN');$t->unsignedBigInteger('minimum_amount_minor')->default(1000);$t->unsignedBigInteger('maximum_amount_minor')->nullable();$t->decimal('interest_rate',8,4)->default(10);$t->unsignedInteger('tenure_days')->default(30);$t->string('repayment_frequency',20)->default('monthly');$t->decimal('late_penalty_rate',8,4)->default(1);$t->boolean('active')->default(true);$t->json('metadata')->nullable();$t->timestamps();}); }
 public function down(): void { Schema::dropIfExists('loan_products'); }
};