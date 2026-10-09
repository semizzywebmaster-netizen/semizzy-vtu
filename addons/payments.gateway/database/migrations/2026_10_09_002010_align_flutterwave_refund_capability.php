<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $provider = DB::table('payment_gateway_providers')->where('code', 'flutterwave')->first();
        if (!$provider) {
            return;
        }

        $capabilities = json_decode((string) $provider->capabilities, true);
        $capabilities = is_array($capabilities) ? $capabilities : [];
        if (in_array('refunds', $capabilities, true) && !in_array('refund', $capabilities, true)) {
            $capabilities[] = 'refund';
            DB::table('payment_gateway_providers')
                ->where('id', $provider->id)
                ->update(['capabilities' => json_encode(array_values(array_unique($capabilities))), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $provider = DB::table('payment_gateway_providers')->where('code', 'flutterwave')->first();
        if (!$provider) {
            return;
        }

        $capabilities = json_decode((string) $provider->capabilities, true);
        $capabilities = is_array($capabilities) ? $capabilities : [];
        $capabilities = array_values(array_filter($capabilities, static fn ($capability) => $capability !== 'refund'));
        DB::table('payment_gateway_providers')
            ->where('id', $provider->id)
            ->update(['capabilities' => json_encode($capabilities), 'updated_at' => now()]);
    }
};
