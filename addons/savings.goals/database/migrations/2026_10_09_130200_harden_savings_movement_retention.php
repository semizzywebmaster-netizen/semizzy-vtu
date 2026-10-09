<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('savings_movements', function (Blueprint $table): void {
            $table->dropForeign(['savings_account_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('savings_account_id')->references('id')->on('savings_accounts')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_movements', function (Blueprint $table): void {
            $table->dropForeign(['savings_account_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('savings_account_id')->references('id')->on('savings_accounts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
