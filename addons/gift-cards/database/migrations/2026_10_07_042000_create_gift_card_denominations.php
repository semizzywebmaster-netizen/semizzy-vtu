<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('gift_card_denominations',function(Blueprint $t){
   $t->id(); $t->foreignId('gift_card_product_id')->constrained('gift_card_products')->cascadeOnDelete();
   $t->string('provider_denomination_code')->nullable()->index(); $t->decimal('face_value',20,2); $t->string('face_currency',8);
   $t->decimal('provider_price',20,2); $t->decimal('sale_price',20,2); $t->string('wallet_currency',8)->default('NGN');
   $t->boolean('enabled')->default(true)->index(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['gift_card_product_id','face_value','face_currency']);
  });
 }
 public function down(): void { Schema::dropIfExists('gift_card_denominations'); }
};