<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communication_campaigns', function (Blueprint $table): void {
            $table->json('result_summary')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('communication_campaigns', function (Blueprint $table): void {
            $table->dropColumn('result_summary');
        });
    }
};
