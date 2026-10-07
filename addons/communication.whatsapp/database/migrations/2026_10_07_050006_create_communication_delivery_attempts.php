<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_delivery_attempts', function (Blueprint $table) {
   $table->id(); $table->foreignId('message_id')->constrained('communication_messages')->cascadeOnDelete();
   $table->foreignId('provider_id')->nullable()->constrained('communication_providers')->nullOnDelete();
   $table->unsignedInteger('attempt')->default(1); $table->string('status',32)->default('pending');
   $table->string('external_message_id',191)->nullable(); $table->text('response')->nullable(); $table->text('error')->nullable();
   $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
   $table->index(['message_id','attempt']); $table->index(['provider_id','status']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_delivery_attempts'); }
};