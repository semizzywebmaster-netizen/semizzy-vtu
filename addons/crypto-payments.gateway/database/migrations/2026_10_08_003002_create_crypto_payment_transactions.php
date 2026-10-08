<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('crypto_payment_provider_id')->nullable()->constrained('crypto_payment_providers')->nullOnDelete();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('reference')->unique();
            $table->string('status')->default('pending')->index();
            $table->string('asset', 32);
            $table->string('network', 64)->nullable();
            $table->decimal('fiat_amount', 36, 8)->nullable();
            $table->string('fiat_currency', 16)->nullable();
            $table->decimal('crypto_amount', 36, 18)->nullable();
            $table->decimal('crypto_received', 36, 18)->nullable();
            $table->decimal('exchange_rate', 36, 18)->nullable();
            $table->decimal('provider_fee', 36, 18)->nullable();
            $table->string('wallet_address')->nullable();
            $table->string('tx_hash')->nullable()->index();
            $table->unsignedInteger('confirmations')->default(0);
            $table->unsignedInteger('required_confirmations')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['crypto_payment_provider_id', 'status']);
            $table->index(['asset', 'network', 'status']);
        });

        Schema::create('crypto_payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_payment_provider_id')->nullable()->constrained('crypto_payment_providers')->nullOnDelete();
            $table->string('event_key')->unique();
            $table->string('event_type')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('signature')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['crypto_payment_provider_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_payment_webhook_events');
        Schema::dropIfExists('crypto_payment_transactions');
    }
};