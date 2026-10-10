<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Repair partially deployed databases without removing or rewriting user data.
        if (Schema::hasTable('user_devices')) {
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

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->json('data');
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
                $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('dashboard_messages')) {
            Schema::create('dashboard_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 20)->index();
                $table->string('title')->nullable();
                $table->text('message');
                $table->json('tiers')->nullable();
                $table->json('audiences')->nullable();
                $table->string('time_period', 20)->nullable()->index();
                $table->string('season_key', 80)->nullable()->index();
                $table->unsignedInteger('priority')->default(0);
                $table->boolean('active')->default(true)->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });

            $now = now();
            DB::table('dashboard_messages')->insert([
                [
                    'type' => 'greeting',
                    'title' => 'Welcome back',
                    'message' => 'Welcome back, :name. Your account is ready when you are.',
                    'priority' => 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'type' => 'quote',
                    'title' => 'Keep building',
                    'message' => 'Build trust with every transaction.',
                    'priority' => 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: this repairs live schema drift and must not
        // drop production tables or authentication fields on rollback.
    }
};
