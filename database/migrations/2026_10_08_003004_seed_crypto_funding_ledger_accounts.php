<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $accounts = [
            ['code' => 'crypto_funding_clearing_ngn', 'name' => 'Crypto Funding Clearing (NGN)', 'type' => 'asset'],
            ['code' => 'user_wallet_liability_ngn', 'name' => 'User Wallet Liability (NGN)', 'type' => 'liability'],
            ['code' => 'crypto_funding_fee_revenue_ngn', 'name' => 'Crypto Funding Fee Revenue (NGN)', 'type' => 'revenue'],
        ];

        foreach ($accounts as $account) {
            DB::table('ledger_accounts')->updateOrInsert(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'currency' => 'NGN',
                    'status' => 'active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('ledger_accounts')->whereIn('code', [
            'crypto_funding_clearing_ngn',
            'user_wallet_liability_ngn',
            'crypto_funding_fee_revenue_ngn',
        ])->delete();
    }
};
