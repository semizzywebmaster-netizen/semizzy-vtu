<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('user_devices')) {
            return;
        }

        $columns = [
            'authenticated_at' => fn (Blueprint $table) => $table->timestamp('authenticated_at')->nullable(),
            'auth_method' => fn (Blueprint $table) => $table->string('auth_method', 30)->nullable(),
            'biometric_enabled' => fn (Blueprint $table) => $table->boolean('biometric_enabled')->default(false),
            'session_token_hash' => fn (Blueprint $table) => $table->string('session_token_hash', 128)->nullable(),
        ];

        foreach ($columns as $name => $addColumn) {
            if (! Schema::hasColumn('user_devices', $name)) {
                Schema::table('user_devices', $addColumn);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_devices')) {
            return;
        }

        $columns = ['authenticated_at', 'auth_method', 'biometric_enabled', 'session_token_hash'];
        $existing = array_values(array_filter($columns, static fn (string $name): bool => Schema::hasColumn('user_devices', $name)));

        if ($existing !== []) {
            Schema::table('user_devices', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
