<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * OTP challenges are owned by the earlier core security migration
     * (2026_10_05_000035_expand_core_account_security).
     *
     * This migration remains as a compatibility marker for environments
     * where the newer OTP service was introduced after that migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('otp_challenges')) {
            return;
        }

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('email');
            $table->string('purpose', 60);
            $table->string('destination', 255)->nullable();
            $table->string('code_hash', 255);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'expires_at']);
            $table->index(['destination', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        // The table is owned by the earlier core security migration.
    }
};
