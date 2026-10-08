<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')
            ->where('code', 'kora')
            ->update([
                'base_url' => 'https://api.korapay.com',
                'capabilities' => json_encode([
                    'collect_payment',
                    'card_payment',
                    'bank_transfer_collection',
                    'webhook',
                    'requery',
                ]),
                'settings' => json_encode([
                    'require_webhook_signature' => true,
                    'signature_header' => 'x-korapay-signature',
                    'webhook_signature_algorithm' => 'sha256_data',
                ]),
            ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')
            ->where('code', 'kora')
            ->update([
                'base_url' => null,
                'capabilities' => json_encode([]),
                'settings' => json_encode([]),
            ]);
    }
};
