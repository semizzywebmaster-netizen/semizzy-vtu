<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('wallet_account_id')->nullable()->constrained('wallet_accounts')->nullOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('provider_reference', 190)->nullable()->index();
            $table->string('provider_id')->nullable()->index();
            $table->string('purpose', 60)->default('wallet_funding');
            $table->char('currency', 3)->default('NGN');
            $table->decimal('amount_minor', 20, 0);
            $table->string('status', 30)->default('pending');
            $table->string('channel', 40)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['provider_id', 'provider_reference']);
        });
    }

    public function down(): void { Schema::dropIfExists('payment_intents'); }
};
