<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bulk_sms_campaigns')) {
            return;
        }

        if (! Schema::hasColumn('bulk_sms_campaigns', 'edit_until')) {
            Schema::table('bulk_sms_campaigns', function (Blueprint $table): void {
                $table->timestamp('edit_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bulk_sms_campaigns') && Schema::hasColumn('bulk_sms_campaigns', 'edit_until')) {
            Schema::table('bulk_sms_campaigns', function (Blueprint $table): void {
                $table->dropColumn('edit_until');
            });
        }
    }
};
