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
                $table->string('idempotency_key', 160)->nullable()->after('reference');
                $table->unique(['user_id', 'idempotency_key'], 'vtu_bulk_user_idempotency_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->dropUnique('vtu_bulk_user_idempotency_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
