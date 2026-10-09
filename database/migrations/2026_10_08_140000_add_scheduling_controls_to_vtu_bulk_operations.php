<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        if (! Schema::hasColumn('vtu_bulk_operations', 'scheduled_at')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->timestamp('scheduled_at')->nullable();
            });
        }

        if (! Schema::hasColumn('vtu_bulk_operations', 'edit_until')) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
                $table->timestamp('edit_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        $columns = [];
        foreach (['scheduled_at', 'edit_until'] as $column) {
            if (Schema::hasColumn('vtu_bulk_operations', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('vtu_bulk_operations', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
