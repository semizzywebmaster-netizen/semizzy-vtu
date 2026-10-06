<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_idempotency_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('idempotency_key', 191);
            $table->string('request_hash', 64);
            $table->string('state', 40)->default('IN_PROGRESS');
            $table->string('internal_reference', 120)->unique();
            $table->string('provider_reference', 255)->nullable();
            $table->string('transaction_status', 60)->nullable();
            $table->json('safe_response')->nullable();
            $table->text('safe_error')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['api_provider_id', 'idempotency_key'], 'provider_idempotency_unique');
            $table->index(['api_provider_id', 'state']);
            $table->index(['request_hash']);
            $table->index(['locked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_idempotency_records');
    }
};