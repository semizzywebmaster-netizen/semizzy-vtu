<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_verified_at')->nullable()->after('phone_verified_at');
            $table->boolean('whatsapp_transaction_enabled')->default(false)->after('whatsapp_verified_at');
            $table->index(['phone','whatsapp_verified_at']);
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone','whatsapp_verified_at']);
            $table->dropColumn(['whatsapp_verified_at','whatsapp_transaction_enabled']);
        });
    }
};
