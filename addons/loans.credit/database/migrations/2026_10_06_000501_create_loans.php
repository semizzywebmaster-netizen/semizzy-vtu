<?php
use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;
return new class extends Migration {
 public function up(): void { Schema::create('loans',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('loan_product_id')->constrained('loan_products');$t->string('reference')->unique();$t->string('currency',3);$t->unsignedBigInteger('principal_minor');$t->unsignedBigInteger('interest_minor')->default(0);$t->unsignedBigInteger('total_due_minor');$t->unsignedBigInteger('repaid_minor')->default(0);$t->string('status',30)->default('pending');$t->timestamp('approved_at')->nullable();$t->timestamp('disbursed_at')->nullable();$t->timestamp('due_at')->nullable();$t->timestamp('closed_at')->nullable();$t->text('rejection_reason')->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['user_id','status']);}); }
 public function down(): void { Schema::dropIfExists('loans'); }
};