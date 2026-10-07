<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_conversations', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('channel',32); $table->string('external_contact',191); $table->string('external_thread_id',191)->nullable();
   $table->string('status',32)->default('open'); $table->string('assigned_to')->nullable();
   $table->timestamp('last_message_at')->nullable(); $table->timestamps();
   $table->unique(['channel','external_contact','external_thread_id']);
   $table->index(['user_id','status']); $table->index(['assigned_to','status']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_conversations'); }
};