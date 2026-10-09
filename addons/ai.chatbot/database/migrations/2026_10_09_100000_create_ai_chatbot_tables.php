<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('ai_chatbot_providers',function(Blueprint $t){$t->id();$t->string('name',120);$t->string('driver',32);$t->text('api_key_encrypted');$t->string('model',160);$t->boolean('enabled')->default(false);$t->unsignedInteger('priority')->default(100);$t->unsignedSmallInteger('timeout_seconds')->default(20);$t->unsignedInteger('max_output_tokens')->default(600);$t->timestamp('last_tested_at')->nullable();$t->string('last_test_status',32)->nullable();$t->text('last_test_message')->nullable();$t->timestamps();$t->index(['enabled','priority']);});
  Schema::create('ai_chatbot_conversations',function(Blueprint $t){$t->id();$t->uuid('uuid')->unique();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->char('visitor_hash',64)->nullable()->index();$t->string('title',180)->default('New conversation');$t->string('status',24)->default('open');$t->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();$t->timestamp('last_message_at')->nullable();$t->timestamps();$t->index(['user_id','status']);});
  Schema::create('ai_chatbot_messages',function(Blueprint $t){$t->id();$t->foreignId('conversation_id')->constrained('ai_chatbot_conversations')->cascadeOnDelete();$t->string('role',24);$t->longText('content');$t->string('provider',40)->nullable();$t->string('model',160)->nullable();$t->unsignedInteger('input_tokens')->nullable();$t->unsignedInteger('output_tokens')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['conversation_id','id']);});
  Schema::create('ai_chatbot_knowledge',function(Blueprint $t){$t->id();$t->string('title',180);$t->string('category',100)->default('general');$t->longText('content');$t->string('source_url',2048)->nullable();$t->boolean('published')->default(false);$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->index(['published','category']);});
  Schema::create('ai_chatbot_settings',function(Blueprint $t){$t->id();$t->string('key',100)->unique();$t->longText('value')->nullable();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('ai_chatbot_settings');Schema::dropIfExists('ai_chatbot_knowledge');Schema::dropIfExists('ai_chatbot_messages');Schema::dropIfExists('ai_chatbot_conversations');Schema::dropIfExists('ai_chatbot_providers');}
};
