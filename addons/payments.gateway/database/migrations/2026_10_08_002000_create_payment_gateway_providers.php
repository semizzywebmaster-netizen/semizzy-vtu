<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('driver');
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->json('capabilities')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('weight')->default(100);
            $table->boolean('enabled')->default(false);
            $table->boolean('paused')->default(false);
            $table->boolean('maintenance')->default(false);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'paused', 'maintenance', 'priority'], 'provider_enabled_state_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_providers');
    }
};