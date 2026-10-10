<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('smm_orders')) {
            return;
        }

        if (! Schema::hasColumn('smm_orders', 'wallet_account_id')) {
            Schema::table('smm_orders', function (Blueprint $table): void {
                $table->foreignId('wallet_account_id')->nullable()->after('user_id')->constrained('wallet_accounts')->restrictOnDelete();
            });
        }

        foreach ([
            'provider_status' => fn (Blueprint $table) => $table->string('provider_status', 80)->nullable(),
            'failure_message' => fn (Blueprint $table) => $table->text('failure_message')->nullable(),
            'processed_at' => fn (Blueprint $table) => $table->timestamp('processed_at')->nullable(),
        ] as $column => $addColumn) {
            if (! Schema::hasColumn('smm_orders', $column)) {
                Schema::table('smm_orders', $addColumn);
            }
        }

        // The wallet binding is used to reconcile each order to the exact wallet
        // that was reserved, rather than resolving a potentially different wallet later.
    }

    public function down(): void
    {
        // Intentionally non-destructive: this migration protects financial reconciliation
        // data and should not remove wallet bindings or provider outcome history on rollback.
    }
};
