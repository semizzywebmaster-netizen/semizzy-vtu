<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fx_rate_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('driver')->default('generic_json');
            $table->string('base_url')->nullable();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('enabled')->default(false);
            $table->boolean('paused')->default(false);
            $table->boolean('maintenance')->default(false);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rate_providers');
    }
};