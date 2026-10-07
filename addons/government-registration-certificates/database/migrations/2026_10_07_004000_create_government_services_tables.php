<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('government_services',function(Blueprint $t){
   $t->id(); $t->string('service_key')->unique(); $t->string('name'); $t->string('agency')->nullable();
   $t->text('description')->nullable(); $t->string('fulfillment_mode')->default('api_or_manual');
   $t->string('provider_reference')->nullable(); $t->decimal('price',18,2)->default(0); $t->char('currency',3)->default('NGN');
   $t->string('status')->default('active'); $t->json('requirements')->nullable(); $t->json('metadata')->nullable();
   $t->timestamps(); $t->index(['agency','status']);
  });
  Schema::create('government_applications',function(Blueprint $t){
   $t->id(); $t->string('reference')->unique(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->foreignId('service_id')->constrained('government_services')->restrictOnDelete(); $t->string('status')->default('draft');
   $t->decimal('amount',18,2)->default(0); $t->char('currency',3)->default('NGN'); $t->string('provider_reference')->nullable();
   $t->string('provider_id')->nullable(); $t->timestamp('submitted_at')->nullable(); $t->timestamp('completed_at')->nullable();
   $t->timestamp('expires_at')->nullable(); $t->json('application_data')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
   $t->index(['user_id','status']); $t->index(['service_id','status']);
  });
  Schema::create('government_documents',function(Blueprint $t){
   $t->id(); $t->foreignId('application_id')->constrained('government_applications')->cascadeOnDelete();
   $t->string('document_type'); $t->string('disk')->default('local'); $t->string('path'); $t->string('original_name')->nullable();
   $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size_bytes')->nullable(); $t->string('status')->default('pending');
   $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable();
   $t->text('review_note')->nullable(); $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['application_id','status']);
  });
  Schema::create('government_certificates',function(Blueprint $t){
   $t->id(); $t->foreignId('application_id')->constrained('government_applications')->cascadeOnDelete();
   $t->string('certificate_type'); $t->string('certificate_number')->nullable()->unique(); $t->string('disk')->default('local');
   $t->string('path')->nullable(); $t->timestamp('issued_at')->nullable(); $t->timestamp('expires_at')->nullable();
   $t->string('status')->default('active'); $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['application_id','status']);
  });
  Schema::create('government_service_settings',function(Blueprint $t){$t->id();$t->string('key')->unique();$t->text('value')->nullable();$t->timestamps();});
 }
 public function down(): void {
  Schema::dropIfExists('government_service_settings'); Schema::dropIfExists('government_certificates');
  Schema::dropIfExists('government_documents'); Schema::dropIfExists('government_applications'); Schema::dropIfExists('government_services');
 }
};