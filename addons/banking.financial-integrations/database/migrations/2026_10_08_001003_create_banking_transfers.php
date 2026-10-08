<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_transfers', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $table->foreignId('bank_directory_id')->nullable()->constrained('banking_bank_directories')->nullOnDelete();
   $table->string('idempotency_key',128)->unique();
   $table->string('transfer_reference',100)->unique();
   $table->string('account_number',64);
   $table->string('account_name')->nullable();
   $table->decimal('amount',20,2);
   $table->string('currency',3)->default('NGN');
   $table->decimal('fee',20,2)->default(0);
   $table->decimal('transfer_fee',20,2)->default(0);
   $table->decimal('vat_fee',20,2)->default(0);
   $table->decimal('other_ng_fee',20,2)->default(0);
   $table->decimal('total_fee',20,2)->default(0);
   $table->decimal('total_debit',20,2)->default(0);
   $table->boolean('requires_otp')->default(false);
   $table->boolean('requires_pin')->default(true);
   $table->timestamp('security_verified_at')->nullable();
   $table->string('provider')->nullable();
   $table->string('provider_reference')->nullable();
   $table->string('status',32)->default('pending');
   $table->string('failure_code')->nullable();
   $table->text('failure_reason')->nullable();
   $table->timestamp('processing_started_at')->nullable();
   $table->timestamp('completed_at')->nullable();
   $table->timestamp('reversed_at')->nullable();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->index(['user_id','status','created_at']);
   $table->index(['provider','provider_reference']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_transfers'); }
};