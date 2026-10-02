<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_service_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('service_key');
            $table->string('provider_service_id')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['api_provider_id', 'service_key']);
            $table->index(['service_key', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_service_mappings');
    }
};
