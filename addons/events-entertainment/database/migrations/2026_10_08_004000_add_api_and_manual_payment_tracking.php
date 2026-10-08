<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('event_orders',function(Blueprint $table){
   $table->string('payment_mode')->default('api')->after('status');
   $table->string('payment_provider')->nullable()->after('payment_mode');
   $table->string('provider_transaction_id')->nullable()->index()->after('payment_provider');
   $table->string('manual_reference')->nullable()->after('provider_transaction_id');
   $table->text('manual_note')->nullable()->after('manual_reference');
   $table->foreignId('manual_recorded_by')->nullable()->constrained('users')->nullOnDelete()->after('manual_note');
   $table->timestamp('payment_confirmed_at')->nullable()->after('paid_at');
   $table->index(['payment_mode','status']);
  });
 }
 public function down():void{
  Schema::table('event_orders',function(Blueprint $table){
   $table->dropForeign(['manual_recorded_by']);$table->dropIndex(['provider_transaction_id']);$table->dropIndex(['payment_mode','status']);
   $table->dropColumn(['payment_mode','payment_provider','provider_transaction_id','manual_reference','manual_note','manual_recorded_by','payment_confirmed_at']);
  });
 }
};