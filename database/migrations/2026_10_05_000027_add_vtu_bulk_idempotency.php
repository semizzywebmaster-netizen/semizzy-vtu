<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->string('idempotency_key', 160)->nullable()->unique()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
