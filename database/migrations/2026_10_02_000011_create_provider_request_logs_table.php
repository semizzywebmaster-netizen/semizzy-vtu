<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up(): void { Schema::create('provider_request_logs', function(Blueprint $table): void {
  $table->id(); $table->foreignId('api_provider_id')->constrained()->cascadeOnDelete();
  $table->string('operation'); $table->string('service_key')->nullable(); $table->string('status');
  $table->string('provider_reference')->nullable()->index(); $table->string('idempotency_key')->nullable()->index();
  $table->unsignedInteger('duration_ms')->nullable(); $table->text('request_summary')->nullable(); $table->text('response_summary')->nullable();
  $table->timestamp('created_at')->useCurrent(); $table->index(['api_provider_id','operation','created_at']);
 });}
 public function down(): void { Schema::dropIfExists('provider_request_logs'); }
};
