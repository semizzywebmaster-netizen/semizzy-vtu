<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('display_name');
            $table->string('official_website')->nullable();
            $table->string('documentation_url')->nullable();
            $table->json('service_categories')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('api_version')->nullable();
            $table->string('auth_type')->default('custom');
            $table->string('environment')->default('sandbox');
            $table->text('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->string('verification_status')->default('pending_verification')->index();
            $table->string('integration_status')->default('draft')->index();
            $table->boolean('enabled')->default(false)->index();
            $table->boolean('paused')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->json('notes')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_summary')->nullable();
            $table->timestamp('last_successful_request_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['enabled', 'paused', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_providers');
    }
};
