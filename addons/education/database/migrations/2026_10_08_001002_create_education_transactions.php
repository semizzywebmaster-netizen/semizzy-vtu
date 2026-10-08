<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('education_transactions', function (Blueprint $table) {
   $table->id();
   $table->uuid('uuid')->unique();
   $table->foreignId('user_id')->constrained()->restrictOnDelete();
   $table->foreignId('education_product_id')->constrained('education_products')->restrictOnDelete();
   $table->foreignId('institution_id')->nullable()->constrained('education_institutions')->nullOnDelete();
   $table->unsignedBigInteger('wallet_account_id')->index();
   $table->string('provider_code',100)->nullable()->index();
   $table->string('provider_reference',150)->nullable()->index();
   $table->string('client_reference',150)->unique();
   $table->string('status',30)->default('pending')->index();
   $table->string('currency',3)->default('NGN');
   $table->unsignedBigInteger('amount_minor');
   $table->unsignedBigInteger('provider_amount_minor')->nullable();
   $table->json('customer_data')->nullable();
   $table->json('provider_data')->nullable();
   $table->text('failure_message')->nullable();
   $table->timestamp('processed_at')->nullable();
   $table->timestamp('next_requery_at')->nullable();
   $table->boolean('requery_required')->default(false);
   $table->timestamps();
   $table->index(['user_id','status']);
   $table->index(['provider_code','provider_reference']);
  });
 }
 public function down(): void { Schema::dropIfExists('education_transactions'); }
};