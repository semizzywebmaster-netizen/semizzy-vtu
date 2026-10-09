<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reward_events')) {
            return;
        }

        Schema::table('reward_events', function (Blueprint $table): void {
            $table->string('approval_status', 20)->default('pending')->after('status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');
            $table->index(['approval_status', 'event_key']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reward_events')) {
            return;
        }

        Schema::table('reward_events', function (Blueprint $table): void {
            $table->dropIndex(['approval_status', 'event_key']);
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at', 'approval_note']);
        });
    }
};