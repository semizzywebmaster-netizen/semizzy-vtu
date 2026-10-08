<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('phone_change_requests', function(Blueprint $table){
   $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->string('current_phone',30); $table->string('requested_phone',30);
   $table->text('reason'); $table->string('screenshot_path')->nullable();
   $table->string('status',20)->default('pending'); $table->text('admin_reason')->nullable();
   $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable();
   $table->timestamps(); $table->index(['user_id','status']);
  });
 }
 public function down(): void { Schema::dropIfExists('phone_change_requests'); }
};