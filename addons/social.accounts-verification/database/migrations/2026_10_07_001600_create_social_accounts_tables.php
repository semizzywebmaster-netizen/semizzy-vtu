<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('social_accounts',function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->string('platform',50); $t->string('username',160); $t->string('account_reference',255)->nullable();
   $t->string('status',30)->default('linked'); $t->string('verification_status',30)->default('unverified');
   $t->string('verification_method',50)->nullable(); $t->string('provider_reference',160)->nullable();
   $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['user_id','platform','username']); $t->index(['platform','verification_status']); $t->index(['provider_reference']);
  });
  Schema::create('social_verification_requests',function(Blueprint $t){
   $t->id(); $t->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
   $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('reference',80)->unique();
   $t->string('method',50); $t->string('status',30)->default('pending'); $t->string('provider_reference',160)->nullable();
   $t->text('evidence')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('expires_at')->nullable();
   $t->timestamp('verified_at')->nullable(); $t->timestamps(); $t->index(['status','expires_at']);
  });
  Schema::create('social_platforms',function(Blueprint $t){
   $t->id(); $t->string('platform_key',80)->unique(); $t->string('name',120);
   $t->string('verification_mode',40)->default('manual'); $t->boolean('active')->default(true);
   $t->json('requirements')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
  });
  Schema::create('social_verification_methods',function(Blueprint $t){
   $t->id(); $t->string('method_key',80)->unique(); $t->string('name',120);
   $t->string('mode',30)->default('manual'); $t->boolean('active')->default(true);
   $t->json('metadata')->nullable(); $t->timestamps();
  });
  Schema::create('social_provider_mappings',function(Blueprint $t){
   $t->id(); $t->foreignId('social_platform_id')->constrained('social_platforms')->cascadeOnDelete();
   $t->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
   $t->string('capability',80); $t->string('provider_service_id',160)->nullable();
   $t->boolean('enabled')->default(false); $t->json('metadata')->nullable(); $t->timestamps();
   $t->unique(['social_platform_id','api_provider_id','capability']); $t->index(['capability','enabled']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('social_provider_mappings'); Schema::dropIfExists('social_verification_methods');
  Schema::dropIfExists('social_platforms'); Schema::dropIfExists('social_verification_requests'); Schema::dropIfExists('social_accounts');
 }
};