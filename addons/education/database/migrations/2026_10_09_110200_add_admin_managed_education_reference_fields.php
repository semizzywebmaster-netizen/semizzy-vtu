<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('education_reference_categories', function (Blueprint $table) {
   $table->id();
   $table->string('kind', 32)->index();
   $table->string('name', 120);
   $table->string('slug', 140);
   $table->text('description')->nullable();
   $table->boolean('is_active')->default(true)->index();
   $table->unsignedInteger('sort_order')->default(0);
   $table->unsignedBigInteger('created_by')->nullable();
   $table->unsignedBigInteger('updated_by')->nullable();
   $table->timestamps();
   $table->unique(['kind', 'slug'], 'edu_ref_category_kind_slug_unique');
  });
  Schema::table('education_reference_catalogue', function (Blueprint $table) {
   $table->unsignedBigInteger('category_id')->nullable()->index();
   $table->unsignedBigInteger('created_by')->nullable();
   $table->unsignedBigInteger('updated_by')->nullable();
  });
 }
 public function down(): void {
  Schema::table('education_reference_catalogue', function (Blueprint $table) {
   $table->dropColumn(['category_id', 'created_by', 'updated_by']);
  });
  Schema::dropIfExists('education_reference_categories');
 }
};
