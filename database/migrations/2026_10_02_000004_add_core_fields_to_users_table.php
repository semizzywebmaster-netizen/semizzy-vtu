<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('USER')->index();
                $table->string('status')->default('active')->index();
                $table->rememberToken();
                $table->timestamps();
            });
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) $table->string('role')->default('USER')->index();
            if (! Schema::hasColumn('users', 'status')) $table->string('status')->default('active')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        if (Schema::hasColumn('users', 'status')) Schema::table('users', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
