<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('education_exam_bodies', function (Blueprint $table) {
   $table->id();
   $table->string('code',100)->unique();
   $table->string('name');
   $table->string('short_name',50)->nullable()->index();
   $table->string('country',3)->default('NG')->index();
   $table->string('type',60)->index();
   $table->text('description')->nullable();
   $table->string('official_website')->nullable();
   $table->string('registration_url')->nullable();
   $table->json('programmes')->nullable();
   $table->json('services')->nullable();
   $table->json('metadata')->nullable();
   $table->boolean('active')->default(true)->index();
   $table->timestamps();
   $table->softDeletes();
  });

  Schema::create('education_past_questions', function (Blueprint $table) {
   $table->id();
   $table->foreignId('exam_body_id')->nullable()->constrained('education_exam_bodies')->nullOnDelete();
   $table->foreignId('institution_id')->nullable()->constrained('education_institutions')->nullOnDelete();
   $table->string('title');
   $table->string('code',100)->nullable()->unique();
   $table->string('subject',150)->nullable()->index();
   $table->string('programme',150)->nullable()->index();
   $table->string('level',100)->nullable()->index();
   $table->string('exam_year',20)->nullable()->index();
   $table->string('session',100)->nullable()->index();
   $table->string('term',50)->nullable();
   $table->string('question_type',50)->default('past_question');
   $table->string('access_type',30)->default('free');
   $table->unsignedBigInteger('price_minor')->nullable();
   $table->string('currency',3)->default('NGN');
   $table->string('file_path')->nullable();
   $table->string('external_url')->nullable();
   $table->text('description')->nullable();
   $table->json('metadata')->nullable();
   $table->boolean('active')->default(true)->index();
   $table->timestamps();
   $table->softDeletes();
   $table->index(['exam_body_id','institution_id']);
  });
 }

 public function down(): void {
  Schema::dropIfExists('education_past_questions');
  Schema::dropIfExists('education_exam_bodies');
 }
};