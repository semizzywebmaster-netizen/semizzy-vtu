<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('gift_card_products',function(Blueprint $t){
   $t->foreignId('gift_card_provider_id')->nullable()->after('id')->constrained('gift_card_providers')->nullOnDelete();
   $t->string('region')->nullable()->after('country_code'); $t->string('provider_currency',8)->nullable()->after('currency');
   $t->index(['gift_card_provider_id','enabled']);
  });
  Schema::table('gift_card_orders',function(Blueprint $t){
   $t->string('wallet_currency',8)->default('NGN')->after('currency');
   $t->json('product_snapshot')->nullable()->after('gift_card_product_id');
   $t->json('provider_snapshot')->nullable()->after('provider_reference');
   $t->timestamp('provider_pending_at')->nullable()->after('failed_at');
  });
  Schema::create('gift_card_deliveries',function(Blueprint $t){
   $t->id(); $t->foreignId('gift_card_order_id')->unique()->constrained('gift_card_orders')->cascadeOnDelete();
   $t->text('code_encrypted'); $t->text('pin_encrypted')->nullable(); $t->string('code_fingerprint',64)->nullable()->unique();
   $t->unsignedInteger('reveal_count')->default(0); $t->timestamp('first_revealed_at')->nullable(); $t->timestamp('last_revealed_at')->nullable();
   $t->timestamp('expires_at')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('gift_card_deliveries');
  Schema::table('gift_card_orders',function(Blueprint $t){$t->dropColumn(['wallet_currency','product_snapshot','provider_snapshot','provider_pending_at']);});
  Schema::table('gift_card_products',function(Blueprint $t){$t->dropForeign(['gift_card_provider_id']);$t->dropIndex(['gift_card_provider_id','enabled']);$t->dropColumn(['gift_card_provider_id','region','provider_currency']);});
 }
};