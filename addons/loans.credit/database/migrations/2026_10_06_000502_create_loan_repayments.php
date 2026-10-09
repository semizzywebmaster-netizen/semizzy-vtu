<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('loan_repayments',function(Blueprint $t){$t->id();$t->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('operation_key')->unique();$t->string('reference')->unique();$t->unsignedBigInteger('amount_minor');$t->string('currency',3);$t->unsignedBigInteger('balance_after_minor');$t->json('metadata')->nullable();$t->timestamps();$t->index(['loan_id','created_at']);}); }
 public function down(): void { Schema::dropIfExists('loan_repayments'); }
};