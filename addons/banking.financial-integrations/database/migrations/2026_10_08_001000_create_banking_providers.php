<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_providers', function (Blueprint $table) {
   $table->id();
   $table->string('name');
   $table->string('driver',64);
   $table->string('base_url')->nullable();
   $table->text('credentials')->nullable();
   $table->json('capabilities')->nullable();
   $table->unsignedInteger('priority')->default(100);
   $table->unsignedInteger('weight')->default(100);
   $table->boolean('enabled')->default(true);
   $table->boolean('paused')->default(false);
   $table->boolean('maintenance')->default(false);
   $table->unsignedInteger('failure_count')->default(0);
   $table->timestamp('cooldown_until')->nullable();
   $table->timestamp('last_success_at')->nullable();
   $table->timestamp('last_failure_at')->nullable();
   $table->timestamp('last_health_check_at')->nullable();
   $table->timestamps();
   $table->index(['enabled','paused','maintenance','priority']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_providers'); }
};