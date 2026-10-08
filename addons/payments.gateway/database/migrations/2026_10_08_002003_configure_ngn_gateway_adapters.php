<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')->where('code', 'paystack')->update([
            'base_url' => 'https://api.paystack.co',
            'capabilities' => json_encode([
                'collect_payment', 'card_payment', 'bank_transfer_collection',
                'webhook', 'requery',
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'x-paystack-signature',
            ]),
            'updated_at' => now(),
        ]);

        DB::table('payment_gateway_providers')->where('code', 'monnify')->update([
            'base_url' => 'https://api.monnify.com',
            'capabilities' => json_encode([
                'collect_payment', 'card_payment', 'bank_transfer_collection',
                'virtual_account', 'account_name_enquiry', 'single_payout',
                'bulk_payout', 'webhook', 'requery', 'settlement',
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'monnify-signature',
            ]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->whereIn('code', ['paystack', 'monnify'])
            ->update([
                'base_url' => null,
                'settings' => json_encode([]),
                'updated_at' => now(),
            ]);
    }
};
