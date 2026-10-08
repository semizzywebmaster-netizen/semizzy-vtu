<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forex_digital_asset_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('driver')->nullable();
            $table->text('credentials')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(false);
            $table->boolean('verified')->default(false);
            $table->boolean('paused')->default(false);
            $table->boolean('maintenance')->default(false);
            $table->boolean('is_market_data_provider')->default(false);
            $table->boolean('is_execution_provider')->default(false);
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'verified', 'is_market_data_provider']);
            $table->index(['enabled', 'verified', 'is_execution_provider']);
        });

        Schema::create('forex_digital_asset_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30); // forex | digital_asset
            $table->string('symbol', 32);
            $table->string('base_code', 20);
            $table->string('quote_code', 20)->nullable();
            $table->string('name');
            $table->string('instrument_type', 40)->default('spot');
            $table->string('status', 20)->default('draft');
            $table->boolean('enabled')->default(false);
            $table->boolean('verified')->default(false);
            $table->string('source_name')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('source_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['category', 'symbol']);
            $table->index(['category', 'status', 'enabled', 'verified']);
        });

        Schema::create('forex_digital_asset_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('forex_digital_asset_instruments')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('forex_digital_asset_providers')->restrictOnDelete();
            $table->decimal('bid', 30, 12)->nullable();
            $table->decimal('ask', 30, 12)->nullable();
            $table->decimal('mid', 30, 12)->nullable();
            $table->decimal('open', 30, 12)->nullable();
            $table->decimal('high', 30, 12)->nullable();
            $table->decimal('low', 30, 12)->nullable();
            $table->decimal('close', 30, 12)->nullable();
            $table->decimal('volume', 38, 12)->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['instrument_id', 'provider_id', 'observed_at']);
            $table->index(['provider_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forex_digital_asset_quotes');
        Schema::dropIfExists('forex_digital_asset_instruments');
        Schema::dropIfExists('forex_digital_asset_providers');
    }
};
