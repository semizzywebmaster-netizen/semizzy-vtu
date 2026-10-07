<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropIndex(['marketplace_orders_buyer_id_status_index']);
            $table->dropIndex(['marketplace_orders_seller_id_status_index']);
            $table->dropColumn(['paid_at', 'cancelled_at', 'refunded_at']);
        });
    }
};
