<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('payment_gateway_providers')->where('code', 'flutterwave')->update([
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
                'require_webhook_signature' => true,
                'signature_header' => 'verif-hash',
                'webhook_signature_algorithm' => 'secret_hash',
                'currency' => 'NGN',
            ]),
            'updated_at' => now(),
        ]);

        DB::table('payment_gateway_providers')->where('code', 'paystack')->update([
            'capabilities' => json_encode([
                'collect_payment',
                'card_payment',
                'bank_transfer_collection',
                'webhook',
                'requery',
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'x-paystack-signature',
                'webhook_signature_algorithm' => 'hmac_sha512_raw',
                'currency' => 'NGN',
            ]),
            'updated_at' => now(),
        ]);

        DB::table('payment_gateway_providers')->where('code', 'monnify')->update([
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
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'monnify-signature',
                'webhook_signature_algorithm' => 'hmac_sha512_raw',
                'currency' => 'NGN',
            ]),
            'updated_at' => now(),
        ]);

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
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'sha512',
                'webhook_signature_algorithm' => 'sha3_512_callback',
                'country' => 'NG',
                'currency' => 'NGN',
                'default_pay_method' => 'BankCard',
            ]),
            'updated_at' => now(),
        ]);

        DB::table('payment_gateway_providers')->where('code', 'kora')->update([
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
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'x-korapay-signature',
                'webhook_signature_algorithm' => 'sha256_data',
                'currency' => 'NGN',
            ]),
            'updated_at' => now(),
        ]);

        DB::table('payment_gateway_providers')->where('code', 'squad')->update([
            'capabilities' => json_encode([
                'collect_payment',
                'card_payment',
                'bank_transfer_collection',
                'webhook',
                'requery',
            ]),
            'settings' => json_encode([
                'require_webhook_signature' => true,
                'signature_header' => 'x-squad-signature',
                'webhook_signature_algorithm' => 'sha512_raw',
                'currency' => 'NGN',
            ]),
            'updated_at' => now(),
        ]);

        // Payaza remains seeded but disabled until its exact merchant API
        // contract is configured and tested; never expose an unverified adapter
        // through automatic failover.
        DB::table('payment_gateway_providers')->where('code', 'payaza')->update([
            'enabled' => false,
            'capabilities' => json_encode([]),
            'settings' => json_encode([
                'adapter_status' => 'not_configured',
            ]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payment_gateway_providers')->whereIn('code', [
            'paystack', 'monnify', 'opay', 'kora', 'squad', 'flutterwave', 'payaza',
        ])->update([
            'capabilities' => json_encode([]),
            'settings' => json_encode([]),
            'updated_at' => now(),
        ]);
    }
};
