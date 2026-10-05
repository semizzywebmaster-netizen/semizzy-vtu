<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('communication_campaigns', function(Blueprint $t): void {
   $t->id(); $t->string('type'); $t->string('title'); $t->text('message'); $t->string('url')->nullable();
   $t->json('targets'); $t->json('channels'); $t->string('status')->default('draft'); $t->timestamp('scheduled_at')->nullable(); $t->timestamp('sent_at')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
   $t->index(['status','scheduled_at']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_campaigns'); }
};