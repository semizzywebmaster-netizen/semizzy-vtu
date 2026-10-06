<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cac_order_documents', function (Blueprint $table): void {
            if (!Schema::hasColumn('cac_order_documents', 'review_note')) {
                $table->text('review_note')->nullable();
            }
            if (!Schema::hasColumn('cac_order_documents', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            }
            if (!Schema::hasColumn('cac_order_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cac_order_documents', function (Blueprint $table): void {
            foreach (['review_note', 'reviewed_by', 'reviewed_at'] as $column) {
                if (Schema::hasColumn('cac_order_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
