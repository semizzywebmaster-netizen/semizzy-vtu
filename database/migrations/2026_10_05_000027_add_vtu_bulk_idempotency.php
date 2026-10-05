<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'vtu_bulk_user_idempotency_unique';

    private function hasUniqueKey(): bool
    {
        return collect(Schema::getIndexes('vtu_bulk_operations'))->contains(function ($index): bool {
            $columns = is_array($index) ? ($index['columns'] ?? []) : ($index->columns ?? []);
            $unique = is_array($index) ? (bool) ($index['unique'] ?? false) : (bool) ($index->unique ?? false);

            return $unique && array_map('strtolower', $columns) === ['user_id', 'idempotency_key'];
        });
    }

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

        if (! $this->hasUniqueKey()) {
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

        $index = collect(Schema::getIndexes('vtu_bulk_operations'))->first(function ($index): bool {
            $columns = is_array($index) ? ($index['columns'] ?? []) : ($index->columns ?? []);
            $unique = is_array($index) ? (bool) ($index['unique'] ?? false) : (bool) ($index->unique ?? false);
            return $unique && array_map('strtolower', $columns) === ['user_id', 'idempotency_key'];
        });

        if ($index) {
            $name = is_array($index) ? ($index['name'] ?? self::INDEX) : ($index->name ?? self::INDEX);
            Schema::table('vtu_bulk_operations', function (Blueprint $table) use ($name): void {
                $table->dropUnique($name);
            });
        }

        if (Schema::hasColumn('vtu_bulk_operations', 'idempotency_key')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
