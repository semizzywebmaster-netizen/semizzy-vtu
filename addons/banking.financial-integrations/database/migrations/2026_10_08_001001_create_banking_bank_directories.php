<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_bank_directories', function (Blueprint $table) {
   $table->id();
   $table->string('provider_code',64)->nullable();
   $table->string('bank_code',32);
   $table->string('name');
   $table->string('short_name')->nullable();
   $table->string('country_code',2)->default('NG');
   $table->string('currency',3)->default('NGN');
   $table->boolean('active')->default(true);
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->unique(['bank_code','country_code']);
   $table->index(['active','country_code']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_bank_directories'); }
};