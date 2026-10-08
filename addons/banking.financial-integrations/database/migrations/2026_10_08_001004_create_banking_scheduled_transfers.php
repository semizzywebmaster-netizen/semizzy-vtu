<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('banking_scheduled_transfers', function (Blueprint $table) {
   $table->id();
   $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
   $table->string('channel',20)->default('bank');
   $table->string('mode',20)->default('single');
   $table->string('status',24)->default('scheduled');
   $table->timestamp('scheduled_for');
   $table->string('currency',3)->default('NGN');
   $table->decimal('total_amount',20,2)->default(0);
   $table->decimal('total_fee',20,2)->default(0);
   $table->string('batch_reference',100)->unique();
   $table->string('idempotency_key',128)->unique();
   $table->timestamp('processing_started_at')->nullable();
   $table->timestamp('completed_at')->nullable();
   $table->timestamp('cancelled_at')->nullable();
   $table->text('failure_reason')->nullable();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->index(['channel','mode','status','scheduled_for']);
  });
 }
 public function down(): void { Schema::dropIfExists('banking_scheduled_transfers'); }
};