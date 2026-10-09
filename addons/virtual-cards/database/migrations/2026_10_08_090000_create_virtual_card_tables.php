<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('virtual_cards',function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->string('provider_key')->nullable(); $t->string('provider_card_id')->nullable();
   $t->string('status')->default('pending'); $t->string('card_type')->default('virtual');
   $t->string('currency',3)->default('NGN'); $t->string('brand')->nullable();
   $t->string('last4',4)->nullable(); $t->text('encrypted_reference')->nullable();
   $t->string('expiry_month',2)->nullable(); $t->string('expiry_year',4)->nullable();
   $t->unsignedBigInteger('spending_limit_minor')->nullable();
   $t->unsignedBigInteger('spent_minor')->default(0);
   $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['provider_key','provider_card_id']);
   $t->index(['user_id','status']);
  });
  Schema::create('virtual_card_transactions',function(Blueprint $t){
   $t->id(); $t->foreignId('virtual_card_id')->constrained('virtual_cards')->cascadeOnDelete();
   $t->string('provider_transaction_id')->nullable(); $t->string('operation_key')->unique();
   $t->string('status')->default('pending'); $t->string('currency',3)->default('NGN');
   $t->unsignedBigInteger('amount_minor'); $t->string('merchant_name')->nullable();
   $t->string('merchant_category')->nullable(); $t->timestamp('occurred_at')->nullable();
   $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['virtual_card_id','status']); $t->index('provider_transaction_id');
  });
 }
 public function down(): void { Schema::dropIfExists('virtual_card_transactions'); Schema::dropIfExists('virtual_cards'); }
};