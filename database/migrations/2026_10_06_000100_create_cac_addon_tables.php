<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cac_orders')) {
            Schema::create('cac_orders', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->string('reference')->unique();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->string('service_type')->index();
                $t->string('status')->default('pending')->index();
                $t->string('idempotency_key')->unique();
                $t->string('customer_name')->nullable();
                $t->string('business_name')->nullable();
                $t->string('company_type')->nullable();
                $t->string('provider_reference')->nullable()->index();
                $t->foreignId('api_provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
                $t->decimal('amount_minor', 20, 0)->default(0);
                $t->decimal('fee_minor', 20, 0)->default(0);
                $t->decimal('total_minor', 20, 0)->default(0);
                $t->char('currency', 3)->default('NGN');
                $t->json('request_payload')->nullable();
                $t->json('response_payload')->nullable();
                $t->json('metadata')->nullable();
                $t->text('failure_message')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->timestamps();
                $t->index(['user_id', 'created_at']);
                $t->index(['service_type', 'status']);
            });
        }

        if (!Schema::hasTable('cac_order_attempts')) {
            Schema::create('cac_order_attempts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cac_order_id')->constrained('cac_orders')->cascadeOnDelete();
                $t->unsignedInteger('attempt_number');
                $t->foreignId('api_provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
                $t->string('operation');
                $t->string('status');
                $t->string('provider_reference')->nullable()->index();
                $t->json('request_payload')->nullable();
                $t->json('response_payload')->nullable();
                $t->string('error_code')->nullable();
                $t->text('error_message')->nullable();
                $t->timestamps();
                $t->unique(['cac_order_id', 'attempt_number']);
            });
        }

        if (!Schema::hasTable('cac_order_documents')) {
            Schema::create('cac_order_documents', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cac_order_id')->constrained('cac_orders')->cascadeOnDelete();
                $t->string('document_type');
                $t->string('storage_disk')->default('local');
                $t->string('storage_path');
                $t->string('original_name')->nullable();
                $t->string('mime_type')->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->string('status')->default('uploaded')->index();
                $t->string('checksum')->nullable();
                $t->timestamps();
                $t->index(['cac_order_id', 'document_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cac_order_documents');
        Schema::dropIfExists('cac_order_attempts');
        Schema::dropIfExists('cac_orders');
    }
};
