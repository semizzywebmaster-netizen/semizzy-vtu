<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $table->string('operation_key');
            $table->string('reference');
            $table->string('type');
            $table->decimal('amount_minor', 20, 0);
            $table->char('currency', 3);
            $table->decimal('available_before_minor', 20, 0);
            $table->decimal('available_after_minor', 20, 0);
            $table->decimal('held_before_minor', 20, 0);
            $table->decimal('held_after_minor', 20, 0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['wallet_account_id', 'operation_key']);
            $table->index(['reference', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('wallet_movements'); }
};
