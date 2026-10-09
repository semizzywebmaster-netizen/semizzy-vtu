<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
            if (!Schema::hasColumn('vtu_bulk_operations', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable();
            }
            if (!Schema::hasColumn('vtu_bulk_operations', 'edit_until')) {
                $table->timestamp('edit_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vtu_bulk_operations')) {
            return;
        }

        Schema::table('vtu_bulk_operations', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('vtu_bulk_operations', 'scheduled_at')) {
                $columns[] = 'scheduled_at';
            }
            if (Schema::hasColumn('vtu_bulk_operations', 'edit_until')) {
                $columns[] = 'edit_until';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
