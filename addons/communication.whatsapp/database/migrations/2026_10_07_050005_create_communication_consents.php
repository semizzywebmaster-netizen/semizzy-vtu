<?php
use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_consents', function (Blueprint $table) {
   $table->id(); $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $table->string('channel',32); $table->string('purpose',64)->default('marketing');
   $table->boolean('opted_in')->default(true); $table->string('source',64)->nullable(); $table->timestamp('consented_at')->nullable(); $table->timestamp('revoked_at')->nullable(); $table->timestamps();
   $table->unique(['user_id','channel','purpose']);
  });
 }
 public function down(): void { Schema::dropIfExists('communication_consents'); }
};