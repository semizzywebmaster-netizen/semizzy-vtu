<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('marketplace_escrows', function (Blueprint $table): void {
   $table->timestamp('fulfillment_due_at')->nullable()->index();
   $table->timestamp('confirmation_due_at')->nullable()->index();
   $table->timestamp('last_reminder_at')->nullable();
  });
  Schema::create('marketplace_escrow_policies', function (Blueprint $table): void {
   $table->id();
   $table->unsignedSmallInteger('physical_dispatch_hours')->default(72);
   $table->unsignedSmallInteger('service_delivery_hours')->default(168);
   $table->unsignedSmallInteger('buyer_confirmation_hours')->default(72);
   $table->boolean('reminders_enabled')->default(true);
   $table->boolean('auto_release_enabled')->default(false);
   $table->unsignedSmallInteger('auto_release_grace_hours')->default(48);
   $table->unsignedSmallInteger('max_dispute_open_days')->default(14);
   $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('marketplace_escrow_policies');
  Schema::table('marketplace_escrows', function (Blueprint $table): void {
   $table->dropColumn(['fulfillment_due_at','confirmation_due_at','last_reminder_at']);
  });
 }
};
