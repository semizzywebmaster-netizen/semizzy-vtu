<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_withdrawal_accounts', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $table->foreignId('bank_directory_id')->constrained('banking_bank_directories')->restrictOnDelete();
   $table->foreignId('account_verification_id')->nullable()->constrained('banking_account_verifications')->nullOnDelete();
   $table->string('account_number',64);
   $table->string('account_name');
   $table->string('currency',3)->default('NGN');
   $table->boolean('kyc_name_matched')->default(false);
   $table->string('kyc_name_snapshot')->nullable();
   $table->boolean('is_default')->default(false);
   $table->boolean('active')->default(true);
   $table->timestamp('verified_at')->nullable();
   $table->timestamps();
   $table->unique(['user_id','bank_directory_id','account_number']);
   $table->index(['user_id','active']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_withdrawal_accounts'); }
};
