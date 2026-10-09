<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void
 {
  Schema::create('ad_types', function (Blueprint $table): void {
   $table->id();
   $table->string('key', 100)->unique();
   $table->string('name', 160);
   $table->text('description')->nullable();
   $table->string('format_family', 40)->default('native');
   $table->json('pricing_models')->nullable();
   $table->json('eligible_surfaces')->nullable();
   $table->boolean('requires_creative')->default(true);
   $table->boolean('supports_targeting')->default(false);
   $table->boolean('requires_integration')->default(false);
   $table->string('integration_key', 120)->nullable();
   $table->boolean('is_system')->default(false);
   $table->boolean('is_active')->default(false)->index();
   $table->timestamps();
  });
 }
 public function down(): void
 {
  Schema::dropIfExists('ad_types');
 }
};
