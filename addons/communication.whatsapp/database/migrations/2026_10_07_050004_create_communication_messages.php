<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_messages', function (Blueprint $table) {
   $table->id(); $table->foreignId('conversation_id')->nullable()->constrained('communication_conversations')->cascadeOnDelete();
   $table->foreignId('campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
   $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('channel',32); $table->string('direction',16); $table->string('recipient',191)->nullable();
   $table->string('external_message_id',191)->nullable(); $table->longText('body')->nullable(); $table->json('metadata')->nullable();
   $table->string('status',32)->default('queued'); $table->string('idempotency_key',191)->nullable();
   $table->timestamp('sent_at')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamp('failed_at')->nullable(); $table->timestamps();
   $table->unique(['channel','idempotency_key']); $table->index(['conversation_id','created_at']); $table->index(['channel','status']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_messages'); }
};