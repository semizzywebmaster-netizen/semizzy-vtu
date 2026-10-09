<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('social_service_orders')) {
            return;
        }

        Schema::table('social_service_orders', function (Blueprint $table): void {
            if (!Schema::hasColumn('social_service_orders', 'payment_status')) {
                $table->string('payment_status', 24)->default('unpaid')->after('status');
            }
            if (!Schema::hasColumn('social_service_orders', 'payment_reference')) {
                $table->string('payment_reference', 80)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('social_service_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_reference');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('social_service_orders')) {
            return;
        }

        Schema::table('social_service_orders', function (Blueprint $table): void {
            foreach (['paid_at', 'payment_reference', 'payment_status'] as $column) {
                if (Schema::hasColumn('social_service_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
