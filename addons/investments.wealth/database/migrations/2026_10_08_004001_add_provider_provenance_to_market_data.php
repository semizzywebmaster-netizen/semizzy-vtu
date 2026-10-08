<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('investment_market_quotes', function (Blueprint $t) {
            $t->foreignId('provider_id')->nullable()->after('security_id')
                ->constrained('investment_providers')->nullOnDelete();
            $t->index(['provider_id', 'observed_at']);
        });

        Schema::table('investment_corporate_actions', function (Blueprint $t) {
            $t->foreignId('provider_id')->nullable()->after('security_id')
                ->constrained('investment_providers')->nullOnDelete();
            $t->index(['provider_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('investment_corporate_actions', function (Blueprint $t) {
            $t->dropForeign(['provider_id']);
            $t->dropIndex(['provider_id', 'payment_date']);
            $t->dropColumn('provider_id');
        });

        Schema::table('investment_market_quotes', function (Blueprint $t) {
            $t->dropForeign(['provider_id']);
            $t->dropIndex(['provider_id', 'observed_at']);
            $t->dropColumn('provider_id');
        });
    }
};