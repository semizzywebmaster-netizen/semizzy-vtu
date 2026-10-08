<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')->where('code', 'opay')->update([
            'capabilities' => json_encode([
                'collect_payment',
                'card_payment',
                'bank_transfer_collection',
                'webhook',
                'requery',
            ]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')->where('code', 'opay')->update([
            'capabilities' => json_encode([
                'collect_payment',
                'card_payment',
                'bank_transfer_collection',
                'webhook',
                'requery',
                'refund',
                'refunds',
            ]),
            'updated_at' => now(),
        ]);
    }
};
