<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kyc_lookup_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('identity_type', 20);
            $table->string('identity_hash', 64);
            $table->string('operation_key', 190)->unique();
            $table->unsignedBigInteger('charge_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_reference', 190)->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->string('wallet_reference', 190)->nullable();
            $table->timestamp('charged_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'identity_type', 'identity_hash']);
            $table->index(['status', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_lookup_charges');
    }
};