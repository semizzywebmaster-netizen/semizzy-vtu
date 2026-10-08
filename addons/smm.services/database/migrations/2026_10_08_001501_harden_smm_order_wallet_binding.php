<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('smm_orders',function(Blueprint $t){
   $t->foreignId('wallet_account_id')->nullable()->after('user_id')->constrained('wallet_accounts')->restrictOnDelete();
   $t->string('provider_status',80)->nullable()->after('provider_reference');
   $t->text('failure_message')->nullable()->after('target');
   $t->timestamp('processed_at')->nullable()->after('completed_at');
   $t->index(['wallet_account_id','status']);
  });
 }
 public function down(): void {Schema::table('smm_orders',function(Blueprint $t){$t->dropForeign(['wallet_account_id']);$t->dropColumn(['wallet_account_id','provider_status','failure_message','processed_at']);});}
};