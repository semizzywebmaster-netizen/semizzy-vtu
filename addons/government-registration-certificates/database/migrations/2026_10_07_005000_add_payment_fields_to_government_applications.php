<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('government_applications',function(Blueprint $t){
   $t->string('payment_reference')->nullable()->unique();
   $t->string('payment_status')->default('unpaid')->index();
   $t->timestamp('paid_at')->nullable();
  });
 }
 public function down(): void {Schema::table('government_applications',function(Blueprint $t){$t->dropUnique(['payment_reference']);$t->dropColumn(['payment_reference','payment_status','paid_at']);});}
};