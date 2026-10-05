<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'vtu_bulk_user_idempotency_unique';

    public function up(): void
    {
        if (! Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        if (! Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->string('idempotency_key', 160)->nullable()->after('reference');
            });
        }

        $hasIndex = collect(Schema::getIndexes('vtu_bulk_operations'))
            ->contains(function ($index): bool {
                $name = is_array($index) ? ($index['name'] ?? null) : ($index->name ?? null);
                return $name === self::INDEX;
            });

        if (! $hasIndex) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->unique(['user_id', 'idempotency_key'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        $hasIndex = collect(Schema::getIndexes('vtu_bulk_operations'))
            ->contains(function ($index): bool {
                $name = is_array($index) ? ($index['name'] ?? null) : ($index->name ?? null);
                return $name === self::INDEX;
            });

        if ($hasIndex) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX);
            });
        }

        if (Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
