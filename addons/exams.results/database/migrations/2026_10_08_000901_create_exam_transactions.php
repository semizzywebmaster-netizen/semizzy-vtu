<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('exam_transactions',function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->foreignId('product_id')->constrained('exam_products')->cascadeOnDelete();
   $t->string('reference',100)->unique(); $t->string('candidate_identifier',120);
   $t->string('candidate_name')->nullable(); $t->string('status',30)->default('pending');
   $t->string('provider_reference',150)->nullable(); $t->unsignedBigInteger('amount_minor');
   $t->string('currency',3)->default('NGN'); $t->text('result_payload')->nullable();
   $t->text('error')->nullable(); $t->string('idempotency_key',120);
   $t->timestamps(); $t->index(['user_id','status']); $t->unique(['user_id','idempotency_key']);
  });
 }
 public function down(): void { Schema::dropIfExists('exam_transactions'); }
};
