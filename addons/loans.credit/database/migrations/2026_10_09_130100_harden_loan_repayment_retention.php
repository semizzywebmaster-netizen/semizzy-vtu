<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['user_id']);
            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
