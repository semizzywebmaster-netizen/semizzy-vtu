<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->timestamp('authenticated_at')->nullable()->after('last_seen_at');
            $table->string('auth_method', 30)->nullable()->after('authenticated_at');
            $table->boolean('biometric_enabled')->default(false)->after('auth_method');
            $table->string('session_token_hash', 128)->nullable()->after('biometric_enabled');
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'revoked_at']);
            $table->dropColumn(['authenticated_at', 'auth_method', 'biometric_enabled', 'session_token_hash']);
        });
    }
};