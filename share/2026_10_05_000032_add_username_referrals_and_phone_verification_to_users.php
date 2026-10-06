<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 40)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->unique()->after('username');
            }
            if (! Schema::hasColumn('users', 'referred_by_id')) {
                $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
        });

        DB::table('users')->whereNull('username')->orderBy('id')->eachById(function ($user): void {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', explode('@', (string) $user->email)[0] ?? '') ?: 'user');
            $base = substr($base, 0, 30);
            $candidate = $base;
            $suffix = 1;
            while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                $candidate = substr($base, 0, 30 - strlen((string) $suffix)) . $suffix;
                $suffix++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        });

        DB::table('users')->whereNull('referral_code')->orderBy('id')->eachById(function ($user): void {
            $code = 'SEM' . strtoupper(base_convert((string) $user->id, 10, 36));
            $candidate = $code;
            $suffix = 1;
            while (DB::table('users')->where('referral_code', $candidate)->where('id', '!=', $user->id)->exists()) {
                $candidate = $code . $suffix;
                $suffix++;
            }
            DB::table('users')->where('id', $user->id)->update(['referral_code' => $candidate]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 40)->nullable(false)->change();
            $table->string('referral_code', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'referred_by_id')) {
                $table->dropForeign(['referred_by_id']);
                $table->dropColumn('referred_by_id');
            }
            foreach (['referral_code', 'username', 'phone_verified_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};