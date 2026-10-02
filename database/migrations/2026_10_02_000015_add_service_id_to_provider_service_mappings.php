<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('provider_service_mappings', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('api_provider_id')->constrained('services')->nullOnDelete();
            $table->index(['service_id','enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_service_mappings', function (Blueprint $table): void {
            $table->dropForeign(['service_id']);
            $table->dropIndex(['service_id','enabled']);
            $table->dropColumn('service_id');
        });
    }
};
