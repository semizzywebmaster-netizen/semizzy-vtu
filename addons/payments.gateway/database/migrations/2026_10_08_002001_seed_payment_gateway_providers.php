<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $providers = [
            ['name' => 'Paystack', 'code' => 'paystack', 'driver' => 'paystack'],
            ['name' => 'OPay', 'code' => 'opay', 'driver' => 'opay'],
            ['name' => 'Monnify', 'code' => 'monnify', 'driver' => 'monnify'],
            ['name' => 'Kora', 'code' => 'kora', 'driver' => 'kora'],
            ['name' => 'Squad', 'code' => 'squad', 'driver' => 'squad'],
            ['name' => 'Flutterwave', 'code' => 'flutterwave', 'driver' => 'flutterwave'],
            ['name' => 'Payaza', 'code' => 'payaza', 'driver' => 'payaza'],
            ['name' => 'Interswitch Payouts', 'code' => 'interswitch', 'driver' => 'interswitch', 'capabilities' => ['account_name_enquiry', 'single_payout', 'bulk_payout']],
        ];

        $capabilities = [
            'collect_payment', 'card_payment', 'bank_transfer_collection',
            'virtual_account', 'account_name_enquiry', 'single_payout',
            'bulk_payout', 'webhook', 'requery', 'settlement',
        ];

        foreach ($providers as $index => $provider) {
            DB::table('payment_gateway_providers')->updateOrInsert(
                ['code' => $provider['code']],
                [
                    'name' => $provider['name'],
                    'driver' => $provider['driver'],
                    'capabilities' => json_encode($provider['capabilities'] ?? $capabilities),
                    'priority' => ($index + 1) * 10,
                    'weight' => 100,
                    'enabled' => false,
                    'paused' => false,
                    'maintenance' => false,
                    'settings' => json_encode([]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->whereIn('code', ['paystack', 'opay', 'monnify', 'kora', 'squad', 'flutterwave', 'payaza', 'interswitch'])
            ->delete();
    }
};