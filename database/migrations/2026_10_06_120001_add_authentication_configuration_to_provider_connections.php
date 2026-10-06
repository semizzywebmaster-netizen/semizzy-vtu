<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_connections', function (Blueprint $table) {
            $table->string('auth_type', 60)->default('none')->after('api_prefix');
            $table->json('auth_options')->nullable()->after('auth_type');
        });
    }

    public function down(): void
    {
        Schema::table('provider_connections', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'auth_options']);
        });
    }
};
