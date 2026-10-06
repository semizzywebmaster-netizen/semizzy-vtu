<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webhook_receipts', function (Blueprint $table): void {
            $table->timestamp('processing_started_at')->nullable()->after('received_at');
            $table->uuid('processing_token')->nullable()->unique()->after('processing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_receipts', function (Blueprint $table): void {
            $table->dropUnique(['processing_token']);
            $table->dropColumn(['processing_started_at', 'processing_token']);
        });
    }
};
