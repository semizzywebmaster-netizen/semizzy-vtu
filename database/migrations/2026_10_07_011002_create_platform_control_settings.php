<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('system_settings')) return;
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 190)->unique();
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};