<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('banking_bank_directories', function (Blueprint $table) {
   $table->string('institution_type',40)->default('bank')->after('short_name');
   $table->string('institution_category',60)->nullable()->after('institution_type');
   $table->string('institution_identifier',100)->nullable()->after('institution_category');
   $table->json('routing_codes')->nullable()->after('metadata');
   $table->index(['institution_type','active']);
  });
 }
 public function down(): void {
  Schema::table('banking_bank_directories', function (Blueprint $table) {
   $table->dropIndex(['institution_type','active']);
   $table->dropColumn(['institution_type','institution_category','institution_identifier','routing_codes']);
  });
 }
};
