<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sim_hosting_products', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->after('network')->constrained('api_providers')->nullOnDelete();
            $table->index(['provider_id','active']);
        });
        Schema::table('sim_hosting_numbers', function (Blueprint $table) {
            $table->string('provider_status', 40)->nullable()->after('status');
            $table->timestamp('provider_reserved_at')->nullable()->after('last_checked_at');
            $table->index(['provider_reference','provider_status']);
        });
    }
    public function down(): void {
        Schema::table('sim_hosting_numbers', function (Blueprint $table) {
            $table->dropIndex(['provider_reference','provider_status']);
            $table->dropColumn(['provider_status','provider_reserved_at']);
        });
        Schema::table('sim_hosting_products', function (Blueprint $table) {
            $table->dropIndex(['provider_id','active']);
            $table->dropConstrainedForeignId('provider_id');
        });
    }
};