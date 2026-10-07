<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_campaigns', function (Blueprint $table) {
   $table->id(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $table->string('name'); $table->string('channel',32); $table->foreignId('template_id')->nullable()->constrained('communication_templates')->nullOnDelete();
   $table->json('audience')->nullable(); $table->longText('content')->nullable();
   $table->string('status',32)->default('draft'); $table->timestamp('scheduled_at')->nullable();
   $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
   $table->index(['channel','status','scheduled_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_campaigns'); }
};