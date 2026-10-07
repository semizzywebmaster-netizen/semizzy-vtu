<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_templates', function (Blueprint $table) {
   $table->id(); $table->string('name'); $table->string('channel',32); $table->string('event')->nullable();
   $table->string('language',16)->default('en'); $table->text('subject')->nullable(); $table->longText('body');
   $table->json('variables')->nullable(); $table->boolean('enabled')->default(true);
   $table->timestamps(); $table->unique(['name','channel','language']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_templates'); }
};