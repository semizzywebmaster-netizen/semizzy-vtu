<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vtu_conversion_requests')) {
            return;
        }

        Schema::create('vtu_conversion_requests', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('reference')->unique();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('conversion_type')->index();
            $t->string('network')->nullable()->index();
            $t->string('status')->default('pending')->index();
            $t->decimal('source_amount_minor', 20, 0)->default(0);
            $t->decimal('target_amount_minor', 20, 0)->default(0);
            $t->decimal('fee_minor', 20, 0)->default(0);
            $t->char('currency', 3)->default('NGN');
            $t->decimal('rate', 20, 8)->nullable();
            $t->json('source_payload')->nullable();
            $t->json('target_payload')->nullable();
            $t->string('receiving_account')->nullable();
            $t->string('proof_path')->nullable();
            $t->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('financial_operation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('settlement_key')->nullable()->unique();
            $t->text('operator_note')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'status', 'created_at']);
            $t->index(['conversion_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtu_conversion_requests');
    }
};
