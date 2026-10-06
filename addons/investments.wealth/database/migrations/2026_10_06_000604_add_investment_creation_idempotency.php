<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->string('creation_idempotency_key', 128)->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->dropUnique(['creation_idempotency_key']);
            $table->dropColumn('creation_idempotency_key');
        });
    }
};
