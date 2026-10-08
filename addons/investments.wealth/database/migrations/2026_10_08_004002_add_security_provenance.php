<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('investment_securities', function (Blueprint $t) {
            $t->string('source_name')->nullable()->after('description');
            $t->string('source_reference')->nullable()->after('source_name');
            $t->string('source_url')->nullable()->after('source_reference');
            $t->timestamp('source_checked_at')->nullable()->after('source_url');
            $t->boolean('verified')->default(false)->after('source_checked_at');
            $t->index(['verified', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('investment_securities', function (Blueprint $t) {
            $t->dropIndex(['verified', 'status']);
            $t->dropColumn(['source_name', 'source_reference', 'source_url', 'source_checked_at', 'verified']);
        });
    }
};