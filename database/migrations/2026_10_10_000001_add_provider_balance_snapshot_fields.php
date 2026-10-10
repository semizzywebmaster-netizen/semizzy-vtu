<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table): void {
            $table->string('balance_amount', 80)->nullable();
            $table->string('balance_currency', 8)->nullable();
            $table->string('balance_status', 32)->nullable();
            $table->text('balance_message')->nullable();
            $table->timestamp('balance_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('api_providers', function (Blueprint $table): void {
            $table->dropColumn(['balance_amount', 'balance_currency', 'balance_status', 'balance_message', 'balance_checked_at']);
        });
    }
};
