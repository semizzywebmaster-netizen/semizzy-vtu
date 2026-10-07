<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_providers', function (Blueprint $table) {
   $table->id(); $table->string('channel',32); $table->string('name'); $table->string('driver',64);
   $table->text('credentials')->nullable(); $table->json('capabilities')->nullable();
   $table->unsignedInteger('priority')->default(100); $table->unsignedInteger('weight')->default(100);
   $table->boolean('enabled')->default(true); $table->boolean('paused')->default(false);
   $table->unsignedInteger('failure_count')->default(0); $table->timestamp('cooldown_until')->nullable();
   $table->timestamp('last_success_at')->nullable(); $table->timestamp('last_failure_at')->nullable();
   $table->timestamps(); $table->index(['channel','enabled','paused','priority']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_providers'); }
};