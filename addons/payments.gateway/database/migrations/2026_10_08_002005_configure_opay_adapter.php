<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')->where('code', 'opay')->update([
            'base_url' => 'https://liveapi.opaycheckout.com',
            'capabilities' => json_encode([
                'collect_payment',
                'card_payment',
                'bank_transfer_collection',
                'webhook',
                'requery',
                'refunds',
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'sha512',
                'country' => 'NG',
                'currency' => 'NGN',
                'default_pay_method' => 'BankCard',
            ]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->where('code', 'opay')
            ->update([
                'base_url' => null,
                'settings' => json_encode([]),
                'updated_at' => now(),
            ]);
    }
};
