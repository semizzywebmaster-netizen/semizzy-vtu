<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $providers = [
            [
                'name' => 'NOWPayments',
                'code' => 'nowpayments',
                'driver' => 'nowpayments',
                'base_url' => 'https://api.nowpayments.io',
                'capabilities' => ['crypto_payment', 'invoice', 'checkout', 'payment_verification', 'webhook', 'crypto_payout', 'mass_payout'],
                'supported_assets' => [],
                'supported_networks' => [],
                'priority' => 10,
            ],
            [
                'name' => 'Binance Pay',
                'code' => 'binance-pay',
                'driver' => 'binance_pay',
                'base_url' => 'https://bpay.binanceapi.com',
                'capabilities' => ['crypto_payment', 'invoice', 'checkout', 'payment_verification', 'webhook', 'refund'],
                'supported_assets' => [],
                'supported_networks' => [],
                'priority' => 20,
            ],
            [
                'name' => 'CoinPayments',
                'code' => 'coinpayments',
                'driver' => 'coinpayments',
                'base_url' => null,
                'capabilities' => ['crypto_payment', 'invoice', 'checkout', 'payment_verification', 'webhook'],
                'supported_assets' => [],
                'supported_networks' => [],
                'priority' => 30,
            ],
        ];

        foreach ($providers as $provider) {
            DB::table('crypto_payment_providers')->updateOrInsert(
                ['code' => $provider['code']],
                [
                    'name' => $provider['name'],
                    'driver' => $provider['driver'],
                    'base_url' => $provider['base_url'],
                    'capabilities' => json_encode($provider['capabilities']),
                    'supported_assets' => json_encode($provider['supported_assets']),
                    'supported_networks' => json_encode($provider['supported_networks']),
                    'priority' => $provider['priority'],
                    'weight' => 100,
                    'enabled' => false,
                    'paused' => false,
                    'maintenance' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('crypto_payment_providers')
            ->whereIn('code', ['nowpayments', 'binance-pay', 'coinpayments'])
            ->delete();
    }
};