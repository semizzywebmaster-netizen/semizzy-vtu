<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            if (!Schema::hasColumn('payment_intents', 'reconciliation_status')) {
                $table->string('reconciliation_status', 30)->default('unreviewed')->index();
            }
            if (!Schema::hasColumn('payment_intents', 'refund_status')) {
                $table->string('refund_status', 30)->default('none')->index();
            }
            if (!Schema::hasColumn('payment_intents', 'refund_reference')) {
                $table->string('refund_reference', 190)->nullable()->index();
            }
            if (!Schema::hasColumn('payment_intents', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            foreach (['reconciliation_status','refund_status','refund_reference','reconciled_at'] as $column) {
                if (Schema::hasColumn('payment_intents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
