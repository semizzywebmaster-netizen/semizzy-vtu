<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  if(!Schema::hasTable('cac_order_status_history')) Schema::create('cac_order_status_history',function(Blueprint $t){
   $t->id(); $t->foreignId('cac_order_id')->constrained('cac_orders')->cascadeOnDelete();
   $t->string('from_status')->nullable(); $t->string('to_status'); $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
   $t->string('source')->default('system'); $t->text('reason')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['cac_order_id','created_at']); $t->index(['to_status','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('cac_order_status_history'); }
};