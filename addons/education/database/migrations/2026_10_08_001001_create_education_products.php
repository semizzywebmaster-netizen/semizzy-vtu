<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('education_products', function (Blueprint $table) {
   $table->id();
   $table->string('code',100)->unique();
   $table->string('name');
   $table->string('category',50)->index();
   $table->foreignId('institution_id')->nullable()->constrained('education_institutions')->nullOnDelete();
   $table->string('provider_service_code',150)->nullable()->index();
   $table->string('currency',3)->default('NGN');
   $table->unsignedBigInteger('provider_amount_minor')->nullable();
   $table->unsignedBigInteger('selling_amount_minor')->nullable();
   $table->string('pricing_mode',30)->default('fixed');
   $table->boolean('requires_institution')->default(false);
   $table->boolean('requires_student_reference')->default(false);
   $table->boolean('requires_session')->default(false);
   $table->boolean('active')->default(true)->index();
   $table->json('fields')->nullable();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->softDeletes();
  });
 }
 public function down(): void { Schema::dropIfExists('education_products'); }
};