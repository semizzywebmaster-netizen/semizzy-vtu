<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3)->default('NGN');
            $table->decimal('available_minor', 20, 0)->default(0);
            $table->decimal('held_minor', 20, 0)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->char('currency', 3)->default('NGN');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->string('type');
            $table->string('status')->default('pending')->index();
            $table->char('currency', 3)->default('NGN');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit_minor', 20, 0)->default(0);
            $table->decimal('credit_minor', 20, 0)->default(0);
            $table->timestamps();
            $table->index(['ledger_account_id', 'created_at']);
        });

        Schema::create('financial_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending')->index();
            $table->decimal('amount_minor', 20, 0);
            $table->char('currency', 3)->default('NGN');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('finance_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_operation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_approvals');
        Schema::dropIfExists('financial_operations');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('wallet_accounts');
    }
};
