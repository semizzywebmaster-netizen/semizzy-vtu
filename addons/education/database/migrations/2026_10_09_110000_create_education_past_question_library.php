<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('education_library_items', function (Blueprint $t) {
   $t->id();
   $t->string('category',40)->index();
   $t->string('title',200); $t->string('slug',220)->unique();
   $t->string('institution',180)->nullable()->index();
   $t->string('faculty',180)->nullable(); $t->string('department',180)->nullable();
   $t->string('course_code',80)->nullable(); $t->string('course_title',180)->nullable();
   $t->string('education_level',80)->nullable(); $t->string('semester',80)->nullable();
   $t->string('academic_session',40)->nullable();
   $t->string('exam_body',100)->nullable()->index(); $t->string('exam_type',100)->nullable();
   $t->string('subject',140)->nullable()->index(); $t->unsignedSmallInteger('exam_year')->nullable()->index();
   $t->text('description')->nullable();
   $t->string('file_path',500); $t->string('file_name',255); $t->string('mime_type',120); $t->unsignedBigInteger('file_size')->default(0);
   $t->string('preview_path',500)->nullable();
   $t->boolean('is_free')->default(true); $t->unsignedBigInteger('price_minor')->default(0); $t->string('currency',3)->default('NGN');
   $t->string('status',20)->default('draft')->index();
   $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('published_at')->nullable(); $t->unsignedBigInteger('downloads_count')->default(0);
   $t->timestamps(); $t->softDeletes();
   $t->index(['category','status','published_at']);
  });
  Schema::create('education_library_purchases', function (Blueprint $t) {
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->foreignId('item_id')->constrained('education_library_items')->restrictOnDelete();
   $t->string('reference',100)->unique(); $t->unsignedBigInteger('amount_minor'); $t->string('currency',3);
   $t->string('status',20)->default('successful'); $t->timestamps();
   $t->unique(['user_id','item_id']); $t->index(['user_id','status']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('education_library_purchases');
  Schema::dropIfExists('education_library_items');
 }
};
