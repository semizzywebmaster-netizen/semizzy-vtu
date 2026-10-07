<?php
use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;
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
 }
 public function down(): void { Schema::dropIfExists('social_verification_requests'); Schema::dropIfExists('social_accounts'); }
};