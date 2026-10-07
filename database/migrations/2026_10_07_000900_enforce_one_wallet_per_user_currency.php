<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wallet_accounts')) {
            return;
        }

        Schema::table('wallet_accounts', function (Blueprint $table): void {
            $table->unique(['user_id', 'currency'], 'wallet_accounts_user_currency_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wallet_accounts')) {
            return;
        }

        Schema::table('wallet_accounts', function (Blueprint $table): void {
            $table->dropUnique('wallet_accounts_user_currency_unique');
        });
    }
};