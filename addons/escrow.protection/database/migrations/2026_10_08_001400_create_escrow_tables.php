<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('escrow_transactions', function(Blueprint $table){
   $table->id();
   $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
   $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
   $table->string('reference',64)->unique();
   $table->string('idempotency_key',120);
   $table->string('currency',3);
   $table->unsignedBigInteger('amount_minor');
   $table->unsignedBigInteger('fee_minor')->default(0);
   $table->string('status',24)->default('funded');
   $table->string('title',190);
   $table->text('description')->nullable();
   $table->timestamp('expires_at')->nullable();
   $table->timestamp('funded_at')->nullable();
   $table->timestamp('released_at')->nullable();
   $table->timestamp('cancelled_at')->nullable();
   $table->timestamp('disputed_at')->nullable();
   $table->timestamp('refunded_at')->nullable();
   $table->json('metadata')->nullable();
   $table->timestamps();
   $table->unique(['buyer_id','idempotency_key']);
   $table->index(['seller_id','status','created_at']);
   $table->index(['buyer_id','status','created_at']);
   $table->index(['status','expires_at']);
  });
  Schema::create('escrow_disputes', function(Blueprint $table){
   $table->id();
   $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
   $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
   $table->string('reason',190);
   $table->text('details')->nullable();
   $table->string('status',24)->default('open');
   $table->text('resolution_note')->nullable();
   $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamp('resolved_at')->nullable();
   $table->timestamps();
   $table->index(['status','created_at']);
  });
 },
 public function down(): void { Schema::dropIfExists('escrow_disputes'); Schema::dropIfExists('escrow_transactions'); }
};