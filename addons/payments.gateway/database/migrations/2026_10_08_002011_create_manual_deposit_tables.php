<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_deposit_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('bank_name', 120);
            $table->string('account_name', 160);
            $table->string('account_number', 30);
            $table->text('instructions')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->boolean('enabled')->default(false)->index();
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::create('manual_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $table->foreignId('method_id')->constrained('manual_deposit_methods')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->decimal('amount_minor', 20, 0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 30)->default('pending')->index();
            $table->string('proof_path', 500);
            $table->string('proof_original_name', 255)->nullable();
            $table->text('user_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['method_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_deposits');
        Schema::dropIfExists('manual_deposit_methods');
    }
};
