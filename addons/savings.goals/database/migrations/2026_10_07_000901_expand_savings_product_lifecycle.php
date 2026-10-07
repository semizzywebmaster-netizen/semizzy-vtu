<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table): void {
            $table->string('contribution_frequency')->nullable()->after('target_amount_minor');
            $table->timestamp('next_contribution_at')->nullable()->after('last_contribution_at');
            $table->unsignedBigInteger('earned_minor')->default(0)->after('balance_minor');
            $table->string('maturity_status')->default('not_matured')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table): void {
            $table->dropColumn(['contribution_frequency', 'next_contribution_at', 'earned_minor', 'maturity_status']);
        });
    }
};