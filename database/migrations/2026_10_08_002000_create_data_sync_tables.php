<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('data_sync_sources', function(Blueprint $table){
   $table->id(); $table->string('dataset_key',120)->unique(); $table->string('addon',120)->index();
   $table->string('label'); $table->string('adapter',120); $table->string('source_url')->nullable();
   $table->boolean('enabled')->default(true)->index(); $table->boolean('requires_review')->default(true);
   $table->unsignedInteger('interval_minutes')->nullable(); $table->timestamp('last_synced_at')->nullable();
   $table->json('metadata')->nullable(); $table->timestamps();
  });
  Schema::create('data_sync_runs', function(Blueprint $table){
   $table->id(); $table->string('dataset_key',120)->index(); $table->string('status',30)->index();
   $table->unsignedInteger('added')->default(0); $table->unsignedInteger('updated')->default(0);
   $table->unsignedInteger('unchanged')->default(0); $table->unsignedInteger('review_required')->default(0);
   $table->unsignedInteger('failed')->default(0); $table->text('message')->nullable();
   $table->json('summary')->nullable(); $table->timestamp('started_at'); $table->timestamp('finished_at')->nullable();
   $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
  });
  Schema::create('payment_banks', function(Blueprint $table){
   $table->id(); $table->string('provider',80)->index(); $table->string('country',2)->default('NG');
   $table->string('name'); $table->string('code',40); $table->string('longcode',80)->nullable();
   $table->string('slug',120)->nullable(); $table->string('gateway',80)->nullable();
   $table->string('currency',10)->default('NGN'); $table->string('type',40)->nullable();
   $table->boolean('active')->default(true)->index(); $table->boolean('is_deleted')->default(false);
   $table->boolean('pay_with_bank')->default(false); $table->boolean('pay_with_bank_transfer')->default(false);
   $table->json('metadata')->nullable(); $table->timestamp('source_updated_at')->nullable();
   $table->timestamps(); $table->unique(['provider','country','code']);
  });
 }
 public function down(): void { Schema::dropIfExists('payment_banks'); Schema::dropIfExists('data_sync_runs'); Schema::dropIfExists('data_sync_sources'); }
};