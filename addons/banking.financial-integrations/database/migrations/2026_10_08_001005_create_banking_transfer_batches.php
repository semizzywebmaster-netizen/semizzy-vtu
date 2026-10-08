<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_transfer_batches', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $table->string('channel',20)->default('bank');
   $table->string('batch_reference',100)->unique();
   $table->string('status',24)->default('pending');
   $table->unsignedInteger('total_items')->default(0);
   $table->unsignedInteger('processed_items')->default(0);
   $table->unsignedInteger('successful_items')->default(0);
   $table->unsignedInteger('failed_items')->default(0);
   $table->decimal('total_amount',20,2)->default(0);
   $table->decimal('total_fee',20,2)->default(0);
   $table->string('idempotency_key',128)->unique();
   $table->timestamp('processing_started_at')->nullable();
   $table->timestamp('completed_at')->nullable();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->index(['channel','status','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_transfer_batches'); }
};