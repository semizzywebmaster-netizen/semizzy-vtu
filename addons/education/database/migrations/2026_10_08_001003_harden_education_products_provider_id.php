<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('education_products', function (Blueprint $table) {
   $table->foreignId('provider_id')->nullable()->after('provider_service_code')->constrained('api_providers')->nullOnDelete();
  });
 }
 public function down(): void {
  Schema::table('education_products', function (Blueprint $table) { $table->dropForeign(['provider_id']); $table->dropColumn('provider_id'); });
 }
};