<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('education_products', function (Blueprint $table) {
   $table->foreignId('exam_body_id')->nullable()->after('institution_id')->constrained('education_exam_bodies')->nullOnDelete();
  });
 }
 public function down(): void {
  Schema::table('education_products', function (Blueprint $table) {
   $table->dropConstrainedForeignId('exam_body_id');
  });
 }
};