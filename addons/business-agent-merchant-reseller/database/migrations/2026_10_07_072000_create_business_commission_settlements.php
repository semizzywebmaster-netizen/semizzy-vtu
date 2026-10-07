<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('business_commission_settlements', function (Blueprint $t) {
   $t->id();
   $t->foreignId('business_partner_id')->constrained('business_partners')->cascadeOnDelete();
   $t->foreignId('beneficiary_partner_id')->constrained('business_partners')->cascadeOnDelete();
   $t->string('transaction_key');
   $t->string('service_key');
   $t->string('product_key')->nullable();
   $t->unsignedBigInteger('base_amount_minor');
   $t->unsignedInteger('rate_bps');
   $t->unsignedBigInteger('amount_minor');
   $t->unsignedInteger('depth');
   $t->string('currency', 3)->default('NGN');
   $t->string('status')->default('pending');
   $t->string('settlement_reference')->nullable()->unique();
   $t->timestamp('settled_at')->nullable();
   $t->json('metadata')->nullable();
   $t->timestamps();
   $t->unique(['transaction_key','beneficiary_partner_id']);
   $t->index(['business_partner_id','status']);
   $t->index(['beneficiary_partner_id','status']);
   $t->index(['service_key','product_key']);
  });
 }
 public function down(): void { Schema::dropIfExists('business_commission_settlements'); }
};