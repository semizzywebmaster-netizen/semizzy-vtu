<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('education_institutions', function (Blueprint $table) {
   $table->id();
   $table->string('code',80)->unique();
   $table->string('name');
   $table->string('type',40)->index();
   $table->string('state',80)->nullable()->index();
   $table->string('lga',100)->nullable();
   $table->string('country',80)->default('Nigeria');
   $table->string('website')->nullable();
   $table->string('external_id',120)->nullable()->index();
   $table->boolean('active')->default(true)->index();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->softDeletes();
  });
 }
 public function down(): void { Schema::dropIfExists('education_institutions'); }
};