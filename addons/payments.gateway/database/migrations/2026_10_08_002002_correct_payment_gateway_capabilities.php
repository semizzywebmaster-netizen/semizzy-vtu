<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')
            ->whereIn('code', ['paystack', 'opay', 'kora', 'squad', 'flutterwave', 'payaza'])
            ->update(['capabilities' => json_encode([])]);

        DB::table('payment_gateway_providers')
            ->where('code', 'monnify')
            ->update([
                'base_url' => 'https://sandbox.monnify.com',
                'capabilities' => json_encode([
                    'collect_payment',
                    'card_payment',
                    'bank_transfer_collection',
                    'virtual_account',
                    'account_name_enquiry',
                    'single_payout',
                    'bulk_payout',
                    'webhook',
                    'requery',
                    'settlement',
                ]),
            ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->whereIn('code', ['paystack', 'opay', 'kora', 'squad', 'flutterwave', 'payaza'])
            ->update([
                'capabilities' => json_encode([
                    'collect_payment', 'card_payment', 'bank_transfer_collection',
                    'virtual_account', 'account_name_enquiry', 'single_payout',
                    'bulk_payout', 'webhook', 'requery', 'settlement',
                ]),
            ]);

        DB::table('payment_gateway_providers')
            ->where('code', 'monnify')
            ->update(['base_url' => null, 'capabilities' => json_encode([])]);
    }
};