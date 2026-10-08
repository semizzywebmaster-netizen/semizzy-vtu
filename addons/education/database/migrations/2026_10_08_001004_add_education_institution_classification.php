<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('education_institutions', function (Blueprint $table) {
   $table->string('category', 60)->default('university')->after('type')->index();
   $table->string('ownership', 30)->default('private')->after('category')->index();
   $table->string('accrediting_body', 40)->nullable()->after('ownership')->index();
   $table->unsignedSmallInteger('established_year')->nullable()->after('accrediting_body');
   $table->string('city', 120)->nullable()->after('lga');
   $table->json('classification')->nullable()->after('metadata');
  });
 }
 public function down(): void {
  Schema::table('education_institutions', function (Blueprint $table) {
   $table->dropColumn(['category','ownership','accrediting_body','established_year','city','classification']);
  });
 }
};
