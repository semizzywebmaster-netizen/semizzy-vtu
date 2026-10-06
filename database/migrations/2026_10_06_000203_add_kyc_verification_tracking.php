<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kyc_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('provider_id')->nullable()->after('rejection_reason');
            $table->string('provider_reference', 190)->nullable()->after('provider_id');
            $table->string('verification_status', 40)->default('not_checked')->after('provider_reference');
            $table->timestamp('verification_checked_at')->nullable()->after('verification_status');
            $table->index(['verification_status', 'verification_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('kyc_applications', function (Blueprint $table): void {
            $table->dropIndex(['verification_status', 'verification_checked_at']);
            $table->dropColumn(['provider_id', 'provider_reference', 'verification_status', 'verification_checked_at']);
        });
    }
};