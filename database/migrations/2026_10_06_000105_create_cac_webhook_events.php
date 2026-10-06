<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('cac_webhook_events', function (Blueprint $t) {
   $t->id(); $t->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
   $t->string('event_id',191); $t->string('event_type',100)->nullable(); $t->string('signature',128)->nullable();
   $t->string('status',30)->default('received'); $t->string('order_reference',191)->nullable()->index();
   $t->json('payload')->nullable(); $t->text('error_message')->nullable(); $t->timestamp('processed_at')->nullable(); $t->timestamps();
   $t->unique(['api_provider_id','event_id']); $t->index(['api_provider_id','created_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('cac_webhook_events'); }
};