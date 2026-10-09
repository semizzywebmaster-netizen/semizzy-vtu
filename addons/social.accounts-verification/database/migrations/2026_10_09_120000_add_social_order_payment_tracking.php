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
            if (!Schema::hasColumn('social_service_orders', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->unique();
            }
            if (!Schema::hasColumn('social_service_orders', 'payment_status')) {
                $table->string('payment_status', 20)->default('pending');
            }
            if (!Schema::hasColumn('social_service_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('social_service_orders')) {
            return;
        }

        Schema::table('social_service_orders', function (Blueprint $table): void {
            $columns = [];
            foreach (['payment_reference', 'payment_status', 'paid_at'] as $column) {
                if (Schema::hasColumn('social_service_orders', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
