<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_account_verifications', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $table->foreignId('bank_directory_id')->nullable()->constrained('banking_bank_directories')->nullOnDelete();
   $table->string('account_number',64);
   $table->string('account_name')->nullable();
   $table->string('provider')->nullable();
   $table->string('provider_reference')->nullable();
   $table->string('request_reference',100)->unique();
   $table->string('status',32)->default('pending');
   $table->text('failure_reason')->nullable();
   $table->timestamp('verified_at')->nullable();
   $table->json('response_meta')->nullable();
   $table->timestamps();
   $table->index(['account_number','bank_directory_id']);
   $table->index(['status','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_account_verifications'); }
};