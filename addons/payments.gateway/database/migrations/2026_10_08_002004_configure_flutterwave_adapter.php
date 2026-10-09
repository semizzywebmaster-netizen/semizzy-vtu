<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')
            ->where('code', 'flutterwave')
            ->update([
                'base_url' => 'https://api.flutterwave.com/v3',
                'capabilities' => json_encode([
                    'collect_payment',
                    'card_payment',
                    'bank_transfer_collection',
                    'account_name_enquiry',
                    'single_payout',
                    'bulk_payout',
                    'webhook',
                    'requery',
                    'refund',
                    'refunds',
                ]),
                'settings' => json_encode([
                    'signature_header' => 'verif-hash',
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->where('code', 'flutterwave')
            ->update([
                'base_url' => null,
                'capabilities' => json_encode([]),
                'settings' => json_encode([]),
                'updated_at' => now(),
            ]);
    }
};
