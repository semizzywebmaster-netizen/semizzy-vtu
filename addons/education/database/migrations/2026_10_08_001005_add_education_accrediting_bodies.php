<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('education_institutions', function (Blueprint $table) {
   $table->json('accrediting_bodies')->nullable()->after('accrediting_body');
  });
 }
 public function down(): void {
  Schema::table('education_institutions', function (Blueprint $table) {
   $table->dropColumn('accrediting_bodies');
  });
 }
};